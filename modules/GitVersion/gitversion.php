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

	class GitVersion {
		private $git_ref = null;
		private $git_branch = null;
		private $git_hash = null;
		private $git_hash_short = null;
		private $file_version = null;
		
		/*
		 * Class constructor.
		 * @param $git_loc The folder where the .git folder is in.
		 * @param #git_loc_full Whether or not the $git_loc is a real path or a relative path.
		 * @param $short_hash_length The short hash size. (Default: 7)
		 * @param $file_version_loc The location of the version file.
		 * @param $file_version_full_loc Whether or not the $file_version_loc is a real path or a relative path.
		 * @return void
		 */
		public function __construct($git_loc, $git_loc_full = false, $short_hash_length = 7, $file_version_loc = null, $file_version_full_loc = false){
			
			$git_loc = ($git_loc_full ? $git_loc : realpath($git_loc . "/.git/"));

			if(!file_exists($git_loc)){
				throw new Exception("Cannot find .git folder. Please make sure the .git folder is at " . $git_loc);
			}
			
			if(!is_integer($short_hash_length)){
				throw new Exception("short_hash_length needs to be a integer.");
			}
			
			$this->git_ref = implode("\n", array_slice(explode("\n", str_replace("ref: ", "", file_get_contents($git_loc . "/HEAD"))), 0, 1));
			$this->git_branch = preg_replace('/^.*\/\s*/', '', $this->git_ref);
			$this->git_hash = implode("\n", array_slice(explode("\n", file_get_contents($git_loc . "/" . $this->git_ref)), 0, 1));
			
			if($short_hash_length > strlen($this->git_hash)){
				throw new Exception("short_hash_length cannot be larger then the hash itself.");
			}
			
			$this->git_hash_short = substr($this->git_hash, 0, $short_hash_length);

			if($file_version_loc != null){

				$file_version_loc = ($file_version_full_loc ? $file_version_loc : realpath($file_version_loc));

				if(!file_exists($file_version_loc)){
					throw new Exception("Cannot find version file. Please make sure the version file is at " . $file_version_loc);
				}
				$this->file_version = file_get_contents($file_version_loc);
			}
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
		public function getVersion($hide_branchname = false){
			return ($this->file_version == null ? "" : $this->file_version . " ") . $this->getShortHash() . ($hide_branchname ? "" : " (" . $this->git_branch . ")");
		}

		/*
		 * Get the version of the version file.
		 * @return string|null
		 */
		public function getFileVersion(){
			return $this->file_version;
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