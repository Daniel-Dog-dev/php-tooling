<?php
    /*
    MIT License

    Copyright (c) 2025 Daniel-Dog-dev

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.
    */

    use Jumbojett\OpenIDConnectClient;

    class Authenticator {

        private $openid_connect = null;
        private $conn = null;
        private $cookiedomain = null;

        /*
         * Constructor for Authenticator class.
         * @param $conn mysqli The MySQLi class for database access.
         * @param $cookiedomain The domain for the auth cookie.
         * @param $openid_url The OpenID URL.
         * @param $openid_client The client name for OpenID.
         * @param $openid_secret The client secret for OpenID.
         */
        public function __construct($conn, $cookiedomain, $openid_url, $openid_client, $openid_secret){

            if(!$conn instanceof mysqli){
                throw new Exception("conn needs to be a mysqli class.", 1);
            }

            if(!is_string($cookiedomain)){
                throw new Exception("cookie domain needs to be a string.", 2);
            }
            if(empty($cookiedomain)){
                throw new Exception("cookie domain needs to have a value", 2);
            }

            if(!is_string($openid_url)){
                throw new Exception("openid URL needs to be a string.", 3);
            }
            if(empty($openid_url)){
                throw new Exception("openid URL needs to have a value", 3);
            }

            if(!is_string($openid_client)){
                throw new Exception("openid Client needs to be a string.", 4);
            }
            if(empty($openid_client)){
                throw new Exception("openid URL needs to have a value", 4);
            }

            if(!is_string($openid_secret)){
                throw new Exception("openid Secret needs to be a string.", 5);
            }
            if(empty($openid_secret)){
                throw new Exception("openid URL needs to have a value", 5);
            }

            $this->openid_connect = new OpenIDConnectClient($openid_url, $openid_client, $openid_secret);
            $this->conn = $conn;
            $this->cookiedomain = $cookiedomain;
        }

        /*
         * Authenticate a user.
         * @return true if authentication is successfull | false if authentication failed.
         */
        public function authenticate(){
            if(!empty($_COOKIE["auth"])){
                if($this->authenticateCookie()) {
                    return true;
                }
            }
            return $this->authenticateOpenID();
        }

        /*
         * Authentication via a cookie token.
         * @return true if authentication is successfull | false if authentication failed.
         */
        private function authenticateCookie(){

            $token = $_COOKIE["auth"];

            if($stmt = $this->conn->prepare("SELECT `users`.`id` FROM `users`, `users_tokens` WHERE `token` = ? AND `users_id` = `users`.`id` AND `valid_till` > CURRENT_TIMESTAMP()")){
				$stmt->bind_param("s", $token);
				$stmt->execute();
				$stmt->bind_result($user_id);
				$stmt->fetch();
				$stmt->close();
				if($user_id == null){
					setcookie("auth", "", time() - 3600, "/", $this->cookiedomain, true, false);
					return false;
				}
                if($stmt2 = $this->conn->prepare("UPDATE `users_tokens` SET `valid_till` = DEFAULT WHERE `token` = ? AND users_id = ?")){
                    $stmt2->bind_param("si", $token, $user_id);
                    $stmt2->execute();
                    $stmt2->close();
                    setcookie("auth", $token, time() + 1800, "/", $this->cookiedomain, true, false);

                    unset($token);
                    unset($stmt);
                    unset($stmt2);
                    unset($user_id);
                    return true;
                }
			}
;           
            unset($token);
            unset($stmt);
            unset($stmt2);
            unset($user_id);
            return false;
        }

        /*
         * Authentication via OpenID.
         * @return true if authentication is successfull | false if authentication failed.
         */
        private function authenticateOpenID(){

            $user_uuid = null;

            if(str_starts_with($this->openid_connect->getRedirectURL(), "https://localhost")){
                $this->openid_connect->setRedirectURL(str_replace("https://", "http://", $this->openid_connect->getRedirectURL()));
            }

            try {
                $this->openid_connect->authenticate();
                $user_uuid = $this->openid_connect->requestUserInfo("sub");
            } catch (Jumbojett\OpenIDConnectClientException $ex) {
                return false;
            }

            if($user_uuid == null){ return false; }

            if($stmt = $this->conn->prepare("SELECT `id` FROM `users` WHERE `uuid` = ?")){
				$stmt->bind_param("s", $user_uuid);
				$stmt->execute();
				$stmt->bind_result($user_id);
				$stmt->fetch();
				$stmt->close();
				if($user_id == null){
					return false;
				}

                $uuid = null;
                if($uuid_result = $this->conn->query("SELECT uuid()")){
                    $uuid = $uuid_result->fetch_row()[0];
			        $uuid_result->close();
                    unset($uuid_result);
                }

                if($stmt = $this->conn->prepare("INSERT INTO `users_tokens` (`id`, `users_id`) VALUES (?,?)")){
                    $stmt->bind_param("si", $uuid, $user_id);
				    $stmt->execute();

                    if($stmt2 = $this->conn->prepare("SELECT `token` FROM users_tokens WHERE `id` = ?")){
                        $stmt2->bind_param("s", $uuid);
                        $stmt2->execute();
                        $stmt2->bind_result($token);
                        $stmt2->fetch();
                        $stmt2->close();

                        setcookie("auth", $token, time() + 1800, "/", $this->cookiedomain, true, false);
                        unset($user_uuid);
                        unset($user_id);
                        unset($stmt);
                        unset($stmt2);
                        unset($uuid);
                        unset($token);
                        return true;
                    }
                }

			} else {
				return false;
			}

            unset($user_uuid);
            unset($user_id);
            unset($stmt);
            unset($stmt2);
            unset($uuid);
            unset($token);
            return false;
        }
    }

?>