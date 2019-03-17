<?php namespace Chitanka;

class RocketChatClient {

	private $chatUrl;
	private $username;
	private $password;
	private $authToken;
	private $userId;
	private $postChannel;
	private $avatar;

	public function __construct(string $chatUrl, string $username = null, string $password = null, string $postChannel = null, string $avatar = null) {
		$this->chatUrl = $chatUrl;
		$this->username = $username;
		$this->password = $password;
		$this->postChannel = $postChannel ?: '#general';
		$this->avatar = $avatar;
	}

	public function canPost() {
		return $this->chatUrl && $this->username && $this->password;
	}

	public function changeUrlScheme($newScheme) {
		$this->chatUrl = preg_replace('#^\w+://#', "$newScheme://", $this->chatUrl);
	}

	public function generatePostMessageScript(string $username, string $password, string $email): string {
		$loginToken = $this->fetchLoginToken($username, $password, $email);
		if (empty($loginToken)) {
			return '<!-- Error: Chat login token could not be generated. -->';
		}
		return "
<script>
window.parent.postMessage({
	event: 'login-with-token',
	loginToken: ".json_encode($loginToken)."
}, ".json_encode($this->chatUrl).");
</script>";
	}

	public function postMessageIfAble($message, $channel = null) {
		if ($this->canPost()) {
			return $this->postMessage($message, $channel);
		}
		return null;
	}

	public function postMessage($message, $channel = null, $avatar = null) {
		$params = ['channel' => $channel ?: $this->postChannel, 'text' => $message];
		$params['avatar'] = $avatar ?? $this->avatar;
		if (substr_count($message, '://') > 1) {
			// a hack: do not generate link preview if more than one links are present
			$params['attachments'] = [];
		}
		return $this->sendAuthenticatedRequest('chat.postMessage', $params);
	}

	protected function normalizeUsername($name) {
		$name = str_replace(' ', '_', $name);
		return $name;
	}

	private function login() {
		if ($this->authToken) {
			return true;
		}
		$loginCacheFile = $this->loginCacheFile();
		if (file_exists($loginCacheFile)) {
			$this->initFromLoginData(json_decode(file_get_contents($loginCacheFile)));
			return true;
		}
		$loginData = $this->sendLoginRequest($this->username, $this->password);
		if ($loginData->status === 'success') {
			$this->initFromLoginData($loginData->data);
			file_put_contents($loginCacheFile, json_encode($loginData->data));
			chmod($loginCacheFile, 0600);
			return true;
		}
		return false;
	}

	private function initFromLoginData($loginData) {
		$this->userId = $loginData->userId;
		$this->authToken = $loginData->authToken;
	}

	private function clearLoginData() {
		unlink($this->loginCacheFile());
		$this->userId = null;
		$this->authToken = null;
	}

	private function loginCacheFile() {
		return sys_get_temp_dir().'/rocketchat_'.md5(implode('.', [$this->chatUrl, $this->username, $this->password])).'.cache';
	}

	private function fetchLoginToken($username, $password, $email = null) {
		$loginResponse = $this->sendLoginRequest($username, $password);
		if ($loginResponse->status === 'success') {
			return $loginResponse->data->authToken;
		}
		if (empty($email)) {
			// we cannot register so we stop here
			return null;
		}
		$registerResponse = $this->sendRegisterRequest($username, $password, $email);
		if ($registerResponse->success) {
			$loginResponse = $this->sendLoginRequest($username, $password);
			if ($loginResponse->status === 'success') {
				return $loginResponse->data->authToken;
			}
		}
		$this->logError(['user' => [$username, $email], 'registerResponse' => $registerResponse]);
		return null;
	}

	private function sendLoginRequest($username, $password) {
		return $this->sendRequest('login', ['user' => $this->normalizeUsername($username), 'password' => $password]);
	}

	private function sendRegisterRequest($username, $password, $email) {
		return $this->sendRequest('users.register', ['username' => $this->normalizeUsername($username), 'email' => $email, 'pass' => $password, 'name' => $username]);
	}

	private function sendAuthenticatedRequest($path, $assocData) {
		$retries = 5;
		for ($i = 0; $i < $retries; $i++) {
			$this->login();
			$response = $this->sendRequest($path, $assocData, [
				"X-Auth-Token: {$this->authToken}",
				"X-User-Id: {$this->userId}",
			]);
			if (!isset($response->status) || $response->status != 'error') {
				// successful request, break the cycle
				break;
			}
			$this->clearLoginData();
			sleep($i ** 3);
		}
		return $response;
	}

	private function sendRequest($path, $assocData, $headers = []) {
		$url = "{$this->chatUrl}/api/v1/{$path}";
		$options = [
			'http' => [
				'ignore_errors' => true,
				'header' => implode("\r\n", array_merge(['Content-Type: application/json'], $headers)),
				'method' => 'POST',
				'content' => json_encode($assocData)
			]
		];
		$response = file_get_contents($url, false, stream_context_create($options));
		if ($response) {
			return json_decode($response);
		}
		$errorResponse = (object) [
			'status' => 'error',
			'url' => $url,
			'data' => $assocData,
			'response' => $response,
			'responseHeaders' => $http_response_header,
		];
		$this->logError($errorResponse);
		return $errorResponse;
	}

	private function logError($error) {
		if (is_array($error)) {
			$error = json_encode($error);
		}
		error_log($error);
	}

}
