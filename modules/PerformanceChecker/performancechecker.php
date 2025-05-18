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

    class PerformanceChecker {
        private $start = array();
        private $stop = array();

        public function start($message = ""){
            $this->start[] = array("time" => microtime(true), "message" => $message);
        }

        public function stop(){
            $this->stop[] = array("time" => microtime(true));
        }

        public function getTimes(){
            $returndata = array();
            foreach($this->start as $key => $data){
                if(!array_key_exists($key, $this->stop)){ 
                    $returndata[$key] = array("time" => $this->start[$key]["time"], "message" => $this->start[$key]["message"]);
                }
                $returndata[$key] = array("time" => (round($this->stop[$key]["time"] - $this->start[$key]["time"], 2)), "message" => $this->start[$key]["message"]);
            }
            return $returndata;
        }

    }

?>