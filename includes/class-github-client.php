<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_GitHub_Client {

	private $token;
	private $owner;
	private $repo;
	private $branch;

	public function __construct() {
		$this->token  = WPJS_Settings::get( 'github_token' );
		$this->owner  = WPJS_Settings::get( 'repo_owner' );
		$this->repo   = WPJS_Settings::get( 'repo_name' );
		$this->branch = WPJS_Settings::get( 'branch', 'main' );
	}

	public function is_configured() {
		return $this->token && $this->owner && $this->repo;
	}

	public function verify() {
		if ( ! $this->token ) {
			return new WP_Error( 'wpjs_no_token', 'No GitHub token configured.' );
		}

		$user = wp_remote_get( 'https://api.github.com/user', array(
			'headers' => $this->headers(),
			'timeout' => 15,
		) );
		if ( is_wp_error( $user ) ) { return $user; }
		$user_code = wp_remote_retrieve_response_code( $user );
		if ( $user_code !== 200 ) {
			return new WP_Error( 'wpjs_bad_token', 'Token rejected by GitHub (HTTP ' . $user_code . ').' );
		}
		$user_data = json_decode( wp_remote_retrieve_body( $user ), true );

		$result = array(
			'login'       => $user_data['login'] ?? '',
			'repo_access' => false,
			'branch_ok'   => false,
		);

		if ( $this->owner && $this->repo ) {
			$repo = wp_remote_get( sprintf( 'https://api.github.com/repos/%s/%s', $this->owner, $this->repo ), array(
				'headers' => $this->headers(),
				'timeout' => 15,
			) );
			if ( ! is_wp_error( $repo ) && wp_remote_retrieve_response_code( $repo ) === 200 ) {
				$result['repo_access'] = true;
				$br = wp_remote_get( sprintf(
					'https://api.github.com/repos/%s/%s/branches/%s',
					$this->owner, $this->repo, rawurlencode( $this->branch )
				), array( 'headers' => $this->headers(), 'timeout' => 15 ) );
				$result['branch_ok'] = ! is_wp_error( $br ) && wp_remote_retrieve_response_code( $br ) === 200;
			}
		}

		return $result;
	}

	public function list_repos() {
		if ( ! $this->token ) {
			return new WP_Error( 'wpjs_no_token', 'No GitHub token configured.' );
		}
		$repos = array();
		for ( $page = 1; $page <= 5; $page++ ) {
			$response = wp_remote_get( add_query_arg( array(
				'per_page'    => 100,
				'page'        => $page,
				'sort'        => 'updated',
				'affiliation' => 'owner,collaborator,organization_member',
			), 'https://api.github.com/user/repos' ), array(
				'headers' => $this->headers(),
				'timeout' => 20,
			) );
			if ( is_wp_error( $response ) ) { return $response; }
			if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return new WP_Error( 'wpjs_repo_list_failed', 'Could not list repos (HTTP ' . wp_remote_retrieve_response_code( $response ) . ').' );
			}
			$batch = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $batch ) || empty( $batch ) ) { break; }
			foreach ( $batch as $r ) {
				$repos[] = array(
					'full_name'      => $r['full_name'] ?? '',
					'default_branch' => $r['default_branch'] ?? 'main',
					'private'        => ! empty( $r['private'] ),
				);
			}
			if ( count( $batch ) < 100 ) { break; }
		}
		return $repos;
	}

	public function list_branches( $owner, $repo ) {
		if ( ! $this->token || ! $owner || ! $repo ) {
			return new WP_Error( 'wpjs_missing', 'Missing token, owner, or repo.' );
		}
		$response = wp_remote_get( sprintf(
			'https://api.github.com/repos/%s/%s/branches?per_page=100',
			$owner, $repo
		), array( 'headers' => $this->headers(), 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) { return $response; }
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_Error( 'wpjs_branch_list_failed', 'Could not list branches.' );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? wp_list_pluck( $data, 'name' ) : array();
	}

	public function put_file( $path, $content, $commit_message ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'wpjs_not_configured', 'GitHub credentials are not configured.' );
		}

		$url      = sprintf( 'https://api.github.com/repos/%s/%s/contents/%s', $this->owner, $this->repo, ltrim( $path, '/' ) );
		$existing = $this->get_file_sha( $path );

		$body = array(
			'message' => $commit_message,
			'content' => base64_encode( $content ),
			'branch'  => $this->branch,
		);
		if ( $existing ) {
			$body['sha'] = $existing;
		}

		$response = wp_remote_request( $url, array(
			'method'  => 'PUT',
			'headers' => $this->headers(),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return new WP_Error(
			'wpjs_github_error',
			sprintf( 'GitHub API error (%d): %s', $code, wp_remote_retrieve_body( $response ) )
		);
	}

	public function delete_file( $path, $commit_message ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'wpjs_not_configured', 'GitHub credentials are not configured.' );
		}

		$sha = $this->get_file_sha( $path );
		if ( ! $sha ) {
			return new WP_Error( 'wpjs_not_found', 'File not found in repository.' );
		}

		$url  = sprintf( 'https://api.github.com/repos/%s/%s/contents/%s', $this->owner, $this->repo, ltrim( $path, '/' ) );
		$body = array(
			'message' => $commit_message,
			'sha'     => $sha,
			'branch'  => $this->branch,
		);

		$response = wp_remote_request( $url, array(
			'method'  => 'DELETE',
			'headers' => $this->headers(),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) { return $response; }
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}
		return new WP_Error( 'wpjs_github_error', sprintf( 'GitHub API error (%d): %s', $code, wp_remote_retrieve_body( $response ) ) );
	}

	public function get_file_content( $path ) {
		$data = $this->get_contents( $path );
		if ( is_wp_error( $data ) ) { return $data; }
		if ( ! isset( $data['content'] ) ) {
			return new WP_Error( 'wpjs_not_file', 'Path is not a file.' );
		}
		return base64_decode( $data['content'] );
	}

	public function list_directory( $path ) {
		$data = $this->get_contents( $path );
		if ( is_wp_error( $data ) ) { return $data; }
		if ( ! is_array( $data ) || ( isset( $data['type'] ) && $data['type'] === 'file' ) ) {
			return new WP_Error( 'wpjs_not_dir', 'Path is not a directory.' );
		}
		$items = array();
		foreach ( $data as $item ) {
			$items[] = array(
				'name' => $item['name'] ?? '',
				'path' => $item['path'] ?? '',
				'type' => $item['type'] ?? 'file',
			);
		}
		return $items;
	}

	private function get_contents( $path ) {
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
			$this->owner, $this->repo, ltrim( $path, '/' ), rawurlencode( $this->branch )
		);
		$response = wp_remote_get( $url, array(
			'headers' => $this->headers(),
			'timeout' => 30,
		) );
		if ( is_wp_error( $response ) ) { return $response; }
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'wpjs_contents_error', 'GitHub API error (' . $code . ').' );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	private function get_file_sha( $path ) {
		$data = $this->get_contents( $path );
		if ( is_wp_error( $data ) ) { return null; }
		return $data['sha'] ?? null;
	}

	private function headers() {
		return array(
			'Authorization' => 'Bearer ' . $this->token,
			'Accept'        => 'application/vnd.github+json',
			'User-Agent'    => 'WP-Jekyll-Sync',
		);
	}
}
