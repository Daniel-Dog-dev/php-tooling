<?php

	class GitVersion {
		private $git_ref = null;
		private $git_branch = null;
		private $git_hash = null;
		private $git_hash_short = null;
		
		/*
		 * Class constructor.
		 * @param $git_loc The folder where the .git folder is in.
		 * @param $short_hash_length The short hash size. (Default: 7)
		 * @return void
		 */
		public function __construct($git_loc, $short_hash_length = 7){
			
			if(!file_exists($git_loc)){
				throw new Exception("Cannot find .git folder. Please make sure the .git folder is at " . __DIR__ . "/../.git/");
			}
			
			$git_loc = realpath($git_loc);
			
			if(!is_integer($short_hash_length)){
				throw new Exception("short_hash_length needs to be a integer.");
			}
			
			$this->git_ref = implode("\n", array_slice(explode("\n", str_replace("ref: ", "", file_get_contents($git_loc . "/.git/HEAD"))), 0, 1));
			$this->git_branch = preg_replace('/^.*\/\s*/', '', $this->git_ref);
			$this->git_hash = implode("\n", array_slice(explode("\n", file_get_contents($git_loc . "/.git/" . $this->git_ref)), 0, 1));
			
			if($short_hash_length > strlen($this->git_hash)){
				throw new Exception("short_hash_length cannot be larger then the hash itself.");
			}
			
			$this->git_hash_short = substr($this->git_hash, 0, $short_hash_length);
		}
		
		/*
		 * Get the short hash value of the ref commit.
		 * @return string.
		 */
		public function getShortHash(){
			return $this->git_hash_short;
		}
		
		/*
		 * Get the hash value of the ref commit.
		 * @return string
		 */
		public function getHash(){
			return $this->git_hash;
		}
		
		/*
		 * Get the ref from the HEAD.
		 */
		public function getRef(){
			return $this->git_ref;
		}

		/*
		 * Get the branch name.
		 */
		public function getBranch(){
			return $this->git_branch;
		}
		
		/*
		 * Get the Git commit short hash and branch name.
		 * @return string
		 */
		public function getVersion(){
			return $this->getShortHash() . " (" . $this->git_branch . ")";
		}
		
		/*
		 * Set the size of the short hash.
		 * @param $short_hash_length The short hash size. (Default: 7)
		 * @return void
		 */
		public function setShortHashSize($short_hash_length = 7){
			if(!is_integer($short_hash_length)){
				throw new Exception("short_hash_length needs to be a integer.");
			}
			
			if($short_hash_length > strlen($this->git_hash)){
				throw new Exception("short_hash_length cannot be larger then the hash itself.");
			}
			
			$this->git_hash_short = substr($this->git_hash, 0, $short_hash_length);
		}
	}

?>