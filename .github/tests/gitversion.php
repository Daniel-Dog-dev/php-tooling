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

    require(__DIR__ . "/../../autoload/autoload.php");

    if(!file_exists(__DIR__ . "/gitinfo.json")){
        echo "No gitinfo.json file found.";
        exit(1);
    }

    $github_runner_gitinfo = json_decode(file_get_contents(__DIR__ . "/gitinfo.json"), true);
    $gitversion = new GitVersion(__DIR__ . "/../../");

    echo "Check if ref matches between GitVersion and gitinfo.json.\n";
    if($gitversion->getRef() != $github_runner_gitinfo["ref"]){
        echo "The Git ref of GitVersion did not match the gitinfo.json ref!\n";
        echo "GitVersion: " . $gitversion->getRef() . "\n";
        echo "gitinfo.json: " . $github_runner_gitinfo["ref"] . "\n";
        exit(2);
    }
    echo "The ref matched between GitVersion and gitinfo.json.\n";

    echo "Check if branch name matches between GitVersion and gitinfo.json.\n";
    if($gitversion->getBranch() != $github_runner_gitinfo["branch"]){
        echo "The Git branch name of GitVersion did not match the gitinfo.json ref!\n";
        echo "GitVersion: " . $gitversion->getBranch() . "\n";
        echo "gitinfo.json: " . $github_runner_gitinfo["branch"] . "\n";
        exit(3);
    }
    echo "The branch name matched between GitVersion and gitinfo.json.\n";

    echo "Check if hash matches between GitVersion and gitinfo.json.\n";
    if($gitversion->getHash() != $github_runner_gitinfo["hash"]){
        echo "The Git hash of GitVersion did not match the gitinfo.json hash!\n";
        echo "GitVersion: " . $gitversion->getHash() . "\n";
        echo "gitinfo.json: " . $github_runner_gitinfo["hash"] . "\n";
        exit(4);
    }
    echo "The hash matched between GitVersion and gitinfo.json.\n";

    echo $gitversion->getVersion();
    exit(0);
?>