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

        private $user_id = null;
        private $user_uuid = null;
        private $user_username = null;
        private $user_email = null;

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
            if($cookiedomain == "localhost"){
                $this->openid_connect->setHttpUpgradeInsecureRequests(false);
            }
            $this->conn = $conn;
            $this->cookiedomain = $cookiedomain;
        }

        /*
         * Authenticate a user.
         * @return true if authentication is successfull | false if authentication failed.
         */
        public function authenticate($auto_redirect = true){
            if(!empty($_COOKIE["auth"])){
                if($this->authenticateCookie()) {
                    return true;
                }
            }
            if($auto_redirect && $this->authenticateOpenID()){
                header("Location: " . $this->openid_connect->getRedirectURL());
                return true;
            }
            return false;
        }

        /*
         * Authentication via a cookie token.
         * @return true if authentication is successfull | false if authentication failed.
         */
        private function authenticateCookie(){

            $token = $_COOKIE["auth"];

            if($stmt = $this->conn->prepare("SELECT `users`.`id`, `users`.`username`, `users`.`email` FROM `users`, `users_tokens` WHERE `token` = ? AND `users_id` = `users`.`id` AND `valid_till` > CURRENT_TIMESTAMP()")){
				$stmt->bind_param("s", $token);
				$stmt->execute();
				$stmt->bind_result($this->user_id, $this->user_username, $this->user_email);
				$stmt->fetch();
				$stmt->close();
                unset($stmt);

				if($this->user_id == null){
					setcookie("auth", "", ['expires' => time() - 1800, 'path' => '/themeparks/v2/endpoint/', 'domain' => $this->cookiedomain, 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
					return false;
				}

                if($stmt = $this->conn->prepare("UPDATE `users_tokens` SET `valid_till` = DEFAULT WHERE `token` = ? AND users_id = ?")){
                    $stmt->bind_param("si", $token, $this->user_id);
                    $stmt->execute();
                    $stmt->close();
                    unset($stmt);
                    setcookie("auth", $token, ['expires' => time() + 1800, 'path' => '/themeparks/v2/endpoint/', 'domain' => $this->cookiedomain, 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);

                    unset($token);
                    return true;
                }
			}
;           
            unset($token);
            return false;
        }

        /*
         * Authentication via OpenID.
         * @return true if authentication is successfull | false if authentication failed.
         */
        private function authenticateOpenID(){
            try {
                if(!$this->openid_connect->authenticate()){
                    return false;
                }
                $this->user_uuid = $this->openid_connect->requestUserInfo("sub");
                $this->user_username = $this->openid_connect->requestUserInfo("preferred_username");
                $this->user_email = $this->openid_connect->requestUserInfo("email");
            } catch (Jumbojett\OpenIDConnectClientException) {
                return false;
            }
            return $this->setAuthCookies();
        }

        private function createUser(){
            if($stmt = $this->conn->prepare("INSERT INTO users (uuid, username, email) VALUES (?,?,?)")){
				$stmt->bind_param("sss", $this->user_uuid, $this->user_username, $this->user_email);
                if(!$stmt->execute()){
                    return false;
                }
                $this->user_id = $stmt->insert_id;
				$stmt->close();
                unset($stmt);
                return true;
            }
            unset($stmt);
            return false;
        }

        /*
         * Get the UUID of the user.
         * @return string|null
         */
        public function getUUID() {
            return $this->user_uuid;
        }

        /**
         * Get the username of the user.
         * @return string|null
         */
        public function getUsername() {
            return $this->user_username;
        }

        /**
         * Get the email of the user.
         * @return string|null
         */
        public function getEmail() {
            return $this->user_email;
        }

        public function getRole(){
            if($stmt = $this->conn->prepare("SELECT `user_role`.`name` FROM `users` LEFT JOIN `roles` `user_role` on `users`.`role` = `user_role`.`id` WHERE `users`.`id` = ?")){
				$stmt->bind_param("i", $this->user_id);
                if(!$stmt->execute()){
                    return false;
                }
                $stmt->bind_result($role);
                $stmt->fetch();
				$stmt->close();
                unset($stmt);
                return $role;
            }
            unset($stmt);
            return null;
        }

        /**
         * Set the refresh token for the auth request.
         * @param string $token The refresh token.
         * @return void
         */
        public function authenticateRefreshToken($token){

            try {
                $this->openid_connect->addScope(array("openid"));
                $this->openid_connect->refreshToken($token);
                $this->user_uuid = $this->openid_connect->requestUserInfo("sub");
                $this->user_username = $this->openid_connect->requestUserInfo("preferred_username");
                $this->user_email = $this->openid_connect->requestUserInfo("email");
                $this->setAuthCookies();
                return true;
            } catch (Jumbojett\OpenIDConnectClientException) {
                return false;
            }
        }

        private function setAuthCookies(){
            if($this->user_uuid == null){ return false; }

            if($stmt = $this->conn->prepare("SELECT `users`.`id`, `users`.`username`, `users`.`email` FROM `users` WHERE `uuid` = ?")){
				$stmt->bind_param("s", $this->user_uuid);
				if(!$stmt->execute()){
                    return false;
                }
				$stmt->bind_result($this->user_id, $username, $email);
				$stmt->fetch();
				$stmt->close();
                unset($stmt);

				if($this->user_id == null){
					if(!$this->createUser()){
                        return false;
                    }
				}
                
                if($this->user_username != $username){
                    if($stmt = $this->conn->prepare("UPDATE users SET username = ? WHERE uuid = ?")){
                        $stmt->bind_param("ss", $this->user_username, $this->user_uuid);
                        $stmt->execute();
                        $stmt->close();
                        unset($stmt);
                    }
                }

                if($this->user_email != $email){
                    if($stmt = $this->conn->prepare("UPDATE users SET email = ? WHERE uuid = ?")){
                        $stmt->bind_param("ss", $this->user_email, $this->user_uuid);
                        $stmt->execute();
                        $stmt->close();
                        unset($stmt);
                    }
                }

                $uuid = null;
                if($uuid_result = $this->conn->query("SELECT uuid()")){
                    $uuid = $uuid_result->fetch_row()[0];
			        $uuid_result->close();
                    unset($uuid_result);
                }

                if($stmt = $this->conn->prepare("INSERT INTO `users_tokens` (`id`, `users_id`) VALUES (?,?)")){
                    $stmt->bind_param("si", $uuid, $this->user_id);
				    if(!$stmt->execute()){
                        return false;
                    }
                    unset($stmt);

                    if($stmt = $this->conn->prepare("SELECT `token` FROM users_tokens WHERE `id` = ?")){
                        $stmt->bind_param("s", $uuid);
                        $stmt->execute();
                        $stmt->bind_result($token);
                        $stmt->fetch();
                        $stmt->close();
                        unset($stmt);

                        setcookie("auth", $token, ['expires' => time() + 1800, 'path' => '/themeparks/v2/endpoint/', 'domain' => $this->cookiedomain, 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
                        setcookie("refresh", $this->openid_connect->getRefreshToken(), ['expires' => time() + 60 * 60 * 8, 'path' => '/themeparks/v2/account/', 'domain' => $this->cookiedomain, 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
                        unset($uuid);
                        unset($token);
                        return true;
                    }
                }
			}
            unset($uuid);
            unset($token);
            return false;
        }
    }

?>