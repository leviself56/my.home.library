<?php
class SimpleAPI {

	protected $auth_header;
	protected $auth_user;
	protected $auth_pass;
	protected $auth_token;
	protected $authentification;
	public	  $caller_id;

	private function getCleanCallerId(): string {
		$raw = $this->caller_id ?? $_SERVER['SCRIPT_NAME'] ?? 'unknown';
		return preg_replace('#index\.php$#', '', $raw);
	}
	
	public function call($request_type, $url, $payload=null, $headers_only=null, $optional_headers=null) {
		// VERIFY WE HAVE AN ACCEPTABLE TYPE:
		if ($request_type == "GET" || $request_type == "POST" ||
			$request_type == "PATCH" || $request_type == "DELETE" ||
			$request_type == "PUT") {

			// BUILD THE COMPLETE API URL
			$complete_url = $url;

			$cURL = curl_init();
			curl_setopt($cURL, CURLOPT_URL, $complete_url);

			if ($request_type == "GET") {
				curl_setopt($cURL, CURLOPT_HTTPGET, true);
			} elseif ($request_type == "POST") {
				curl_setopt($cURL, CURLOPT_POST, true);
				if (isset($payload)) {
					curl_setopt($cURL, CURLOPT_POSTFIELDS, json_encode($payload));
				}
			} elseif ($request_type == "PATCH") {
				curl_setopt($cURL, CURLOPT_CUSTOMREQUEST, 'PATCH');
				curl_setopt($cURL, CURLOPT_POSTFIELDS, json_encode($payload));
			} elseif ($request_type == "PUT") {
				curl_setopt($cURL, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($cURL, CURLOPT_POSTFIELDS, json_encode($payload));
			} elseif ($request_type == "DELETE") {
				curl_setopt($cURL, CURLOPT_CUSTOMREQUEST, "DELETE");
				if (isset($payload)) {
					curl_setopt($cURL, CURLOPT_POSTFIELDS, json_encode($payload));
				}
			}

			curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
/*
			if (isset($this->authentification)) {
				curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Accept: application/json',
					$this->auth_header));
			} else {
				curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Accept: application/json'));
			}*/

			if (isset($headers_only) && $headers_only == true) {
				curl_setopt($cURL, CURLOPT_HEADER, true);
				curl_setopt($cURL, CURLOPT_NOBODY, true);
				curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($cURL, CURLOPT_ENCODING, true);
				curl_setopt($cURL, CURLOPT_AUTOREFERER, true);
			}

			$headers = array(
				'Content-Type: application/json',
				'Accept: application/json',
				'X-Caller: '.$this->getCleanCallerId()
			);

			if (isset($this->authentification)) {
				$headers[] = $this->auth_header;
			}

			if (isset($optional_headers)) {
				if (is_array($optional_headers)) {
					foreach ($optional_headers as $new_header_entry) {
						$headers[] = $new_header_entry;
					}
				} else {
					$headers[] = $optional_headers;
				}				
			}
			curl_setopt($cURL, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($cURL, CURLOPT_REFERER, $this->getCleanCallerId());


			$user_agent	=	"Php/7.0 (Debian) SimpleAPI";
			curl_setopt($cURL, CURLOPT_USERAGENT, $user_agent);
			curl_setopt($cURL, CURLINFO_HEADER_OUT, true);
				
			$result = curl_exec($cURL);
			$header  = curl_getinfo($cURL);
			curl_close($cURL);

			$json = json_decode($result, true);

			// ERROR CATCHING
			if ($header['http_code'] == "400" || $header['http_code'] == "404" || $header['http_code'] == "409") {
				$error = array("error" =>	array(
					"http_code"	=>	$header['http_code'],
					"url"		=>	$header['url'],
					"header"	=>	$header,
					"result"	=>	$result
				));
				return $error;
			}
			
			if (isset($headers_only) && $headers_only == true) {
				$output = array(
					"request_headers"	=>	$headers,
					"response_headers"	=>	$header,
					"payload"			=>	$payload,
					"result"			=>	$result
				);
				return $output;
			} else {
				return $json;
			}
		} else {
			$error = "INCORRECT REQUEST TYPE: ".$request_type;
			return $error;
		}
	}

	public function Auth($array) {
		// DEFINE ARRAY AS
		// TYPE (BASIC | TOKEN), TOKEN, USER, PASS
		// array(
		// 	"type"	=>	"basic",
		// 	"user"	=>	"admin",
		// 	"pass"	=>	"SimplePass");
		//
		// 	OR
		// array(
		// 	"type"	=>	"token",
		// 	"token"	=>	"1V3h5Rn.gc$",
		//	"bearer"=>	true|false);
		switch ($array['type']) {
			case "basic":
				$type = "basic";
				$user = $array['user'];
				$pass = $array['pass'];
				break;
			case "token":
				$type 	= "token";
				$token	= $array['token'];
				$bearer	= (isset($array['bearer'])) ? $array['bearer'] : null;
				break;
		}

		$this->auth_type = $type;
		if (isset($user)) {
			$this->auth_user = $user;
			$this->auth_pass = $pass;
			$base64		 = base64_encode("$user:$pass");
			$this->auth_header="Authorization: Basic $base64";
		}
		if (isset($token)) {
			$this->auth_token= $token;
			if ($bearer == "true" || $bearer == null) {
				$this->auth_header="Authorization: Bearer $token";
			} elseif ($bearer == "false") {
				$this->auth_header="Authorization: $token";				
			}
		}
		$this->authentification = true;
	}
}
?>
