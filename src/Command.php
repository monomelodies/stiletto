<?php

namespace Monomelodies\Stiletto;

use Monolyth\Cliff;
use Ansi;

class Command extends Cliff\Command
{
    public array $source = [];

    public function __invoke(string $cssDir) : void
    {
        if (!$this->source) {
            $this->source = [$cssDir];
        }
        $this->walkCssDir($cssDir);
    }

    private function walkCssDir(string $dir) : void
    {
        $d = dir($dir);
        while (false !== ($entry = $d->read())) {
            if (in_array($entry, ['.', '..'])) {
                continue;
            }
            if (is_dir("$dir/$entry")) {
                $this->walkCssDir("$dir/$entry");
            } elseif (preg_match("@\.(css|scss|less)$@", $entry)) {
                $css = file_get_contents("$dir/$entry");
                preg_match_all("@[\.#]\w+@", $css, $matches, PREG_SET_ORDER);
                $rules = [];
                foreach ($matches as $match) {
                    $rules[] = $match[0];
                }
                $rules = array_unique($rules);
                foreach ($rules as $rule) {
                    $occurencs = false;
                    foreach ($this->source as $sourcedir) {
                        if ($this->walkSourceDir($sourcedir, $rule)) {
                            continue 2;
                        }
                    }
                    echo Ansi::tagsToColors("In <red>$dir/$entry<reset>: <green>$rule<reset>\n");
                }
            }
        }
    }

    private function walkSourceDir(string $dir, string $rule) : bool
    {
        $d = dir($dir);
        while (false !== ($entry = $d->read())) {
            if (in_array($entry, ['.', '..'])) {
                continue;
            }
            if (is_dir("$dir/$entry")) {
                if ($this->walkSourceDir("$dir/$entry", $rule)) {
                    return true;
                }
            } elseif (preg_match("@\.(css|scss|less)$@", $entry)) {
                continue;
            } elseif (preg_match('@\.jsx?$@', $entry)) {
                $code = file_get_contents("$dir/$entry");
                if (strpos($code, $rule) !== false) {
                    return true;
                }
            } else {
                $code = file_get_contents("$dir/$entry");
                if (substr($rule, 0, 1) == '#') {
                    if (strpos($code, 'id="'.substr($rule, 1).'"') !== false) {
                        return true;
                    }
                } else {
                    $class = substr($rule, 1);
                    if (preg_match('@["\s]'.$class.'["\s]@', $code)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

