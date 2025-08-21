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
                throw new Exception("openid Client needs to have a value", 4);
            }

            if(!is_string($openid_secret)){
                throw new Exception("openid Secret needs to be a string.", 5);
            }
            if(empty($openid_secret)){
                throw new Exception("openid Secret needs to have a value", 5);
            }

            $this->openid_connect = new OpenIDConnectClient($openid_url, $openid_client, $openid_secret);
            if($cookiedomain == "localhost" || str_ends_with($cookiedomain, ".local")){
                $this->openid_connect->setHttpUpgradeInsecureRequests(false);
            }
            $this->conn = $conn;
            $this->cookiedomain = $cookiedomain;
        }

        /**
         * Login via OpenID provider.
         * @return bool
         */
        public function login(){
            if($this->authenticateOpenID()){
                return true;
            }
            return false;
        }

        public function getRedirectURL(){
            return $this->openid_connect->getRedirectURL();
        }

        /*
         * Authenticate a user.
         * @return true if authentication is successfull | false if authentication failed.
         */
        public function authenticate(){
            $access_token = empty($_COOKIE["access_token"]) ? null : $_COOKIE["access_token"];
            $refresh_token = empty($_COOKIE["refresh_token"]) ? null : $_COOKIE["refresh_token"];

            if($access_token !== null && $this->authenticateAccessToken($access_token)) {
                unset($access_token);
                unset($refresh_token);
                return true;
            }
            unset($access_token);

            if($refresh_token !== null && $this->authenticateRefreshToken($refresh_token)) {
                unset($refresh_token);
                return true;
            }
            unset($refresh_token);
            
            return false;
        }

        /*
         * Authentication via a cookie token.
         * @return true if authentication is successfull | false if authentication failed.
         */
        private function authenticateAccessToken($access_token){
            $token_data = $this->openid_connect->introspectToken($access_token);

            if($token_data->active && $token_data->exp >= (time() + 30)){
                $this->openid_connect->setAccessToken($access_token);
                $this->user_uuid = $this->openid_connect->requestUserInfo("sub");
                $this->user_username = $this->openid_connect->requestUserInfo("preferred_username");
                $this->user_email = $this->openid_connect->requestUserInfo("email");
                unset($token_data);
                return true;
            }
            unset($token_data);
            
            return false;
        }

        /*
         * Authentication via OpenID.
         * @return true if authentication is successfull | false if authentication failed.
         */
        private function authenticateOpenID(){
            try {
                if($this->openid_connect->authenticate()){
                    $this->user_uuid = $this->openid_connect->requestUserInfo("sub");
                    $this->user_username = $this->openid_connect->requestUserInfo("preferred_username");
                    $this->user_email = $this->openid_connect->requestUserInfo("email");
                    if($this->checkLocalUser()){
                        $this->setAuthCookies();
                        return true;
                    }
                    return false;
                }
                $this->setAuthCookies(true);
                return false;
            } catch (Jumbojett\OpenIDConnectClientException) {
                $this->setAuthCookies(true);
                return false;
            }
            return false;
        }

        private function checkLocalUser(){
            if($this->user_uuid === null || $this->user_username === null || $this->user_email === null){
                return false;
            }

            if($stmt = $this->conn->prepare("SELECT `users`.`id`, `users`.`username`, `users`.`email` FROM `users` WHERE `uuid` = ?")){
				$stmt->bind_param("s", $this->user_uuid);
				if(!$stmt->execute()){
                    unset($stmt);
                    return false;
                }
				$stmt->bind_result($user_id, $username, $email);
				$stmt->fetch();
				$stmt->close();
                unset($stmt);

				if($user_id === null){
					if($stmt = $this->conn->prepare("INSERT INTO users (uuid, username, email) VALUES (?,?,?)")){
                        $stmt->bind_param("sss", $this->user_uuid, $this->user_username, $this->user_email);
                        if(!$stmt->execute()){
                            unset($stmt);
                            unset($user_id);
                            unset($username);
                            unset($email);
                            return false;
                        }
                        $stmt->close();
                        unset($stmt);
                        unset($user_id);
                        unset($username);
                        unset($email);
                        return true;
                    }
                    unset($stmt);
                    unset($user_id);
                    unset($username);
                    unset($email);
                    return false;
				}
                
                if($this->user_username != $username){
                    if($stmt = $this->conn->prepare("UPDATE users SET username = ? WHERE uuid = ?")){
                        $stmt->bind_param("ss", $this->user_username, $this->user_uuid);
                        if(!$stmt->execute()){
                            unset($stmt);
                            unset($user_id);
                            unset($username);
                            unset($email);
                            return false;
                        }
                        $stmt->close();
                        unset($stmt);
                        unset($user_id);
                        unset($username);
                        unset($email);
                    }
                    unset($stmt);
                    unset($user_id);
                    unset($username);
                    unset($email);
                }

                if($this->user_email != $email){
                    if($stmt = $this->conn->prepare("UPDATE users SET email = ? WHERE uuid = ?")){
                        $stmt->bind_param("ss", $this->user_email, $this->user_uuid);
                        if(!$stmt->execute()){
                            unset($stmt);
                            unset($user_id);
                            unset($username);
                            unset($email);
                            return false;
                        }
                        $stmt->close();
                        unset($stmt);
                        unset($user_id);
                        unset($username);
                        unset($email);
                    }
                    unset($stmt);
                    unset($user_id);
                    unset($username);
                    unset($email);
                }

                return true;
			}

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

        /**
         * Get the role of the user.
         * @return string|null
         */
        public function getRole(){
            if($stmt = $this->conn->prepare("SELECT `user_role`.`name` FROM `users` LEFT JOIN `roles` `user_role` on `users`.`role` = `user_role`.`id` WHERE `users`.`uuid` = ?")){
				$stmt->bind_param("s", $this->user_uuid);
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
        public function authenticateRefreshToken($refresh_token){
            try {
                if($this->openid_connect->introspectToken($refresh_token)->active){
                    $this->openid_connect->addScope(array("openid"));
                    $this->openid_connect->refreshToken($refresh_token);
                    $this->user_uuid = $this->openid_connect->requestUserInfo("sub");
                    $this->user_username = $this->openid_connect->requestUserInfo("preferred_username");
                    $this->user_email = $this->openid_connect->requestUserInfo("email");
                    $this->setAuthCookies();
                    return true;
                }
            } catch (Jumbojett\OpenIDConnectClientException) {
                $this->setAuthCookies(true);
                return false;
            }
            
            return false;
        }

        private function setAuthCookies($delete_cookies = false){
            if($this->openid_connect->getAccessToken() !== null && !$delete_cookies){
                setcookie("access_token", $this->openid_connect->getAccessToken(), ['expires' => $this->openid_connect->introspectToken($this->openid_connect->getAccessToken())->exp, 'path' => '/', 'domain' => $this->cookiedomain, 'secure' => ($this->cookiedomain == "localhost" || str_ends_with($this->cookiedomain, ".local") ? false : true), 'httponly' => true, 'samesite' => 'Strict']);
            } else {
                setcookie("access_token", "", ['expires' => time() - 1800, 'path' => '/', 'domain' => $this->cookiedomain, 'secure' => ($this->cookiedomain == "localhost" || str_ends_with($this->cookiedomain, ".local") ? false : true), 'httponly' => true, 'samesite' => 'Strict']);
            }

            if($this->openid_connect->getRefreshToken() !== null && !$delete_cookies){
                setcookie("refresh_token", $this->openid_connect->getRefreshToken(), ['expires' => $this->openid_connect->introspectToken($this->openid_connect->getRefreshToken())->exp, 'path' => '/', 'domain' => $this->cookiedomain, 'secure' => ($this->cookiedomain == "localhost" || str_ends_with($this->cookiedomain, ".local") ? false : true), 'httponly' => true, 'samesite' => 'Strict']);
            } else {
                setcookie("refresh_token", "", ['expires' => time() - 1800, 'path' => '/', 'domain' => $this->cookiedomain, 'secure' => ($this->cookiedomain == "localhost" || str_ends_with($cookiedomain, ".local") ? false : true), 'httponly' => true, 'samesite' => 'Strict']);
            }
        }
    }

?>