<?php

namespace Monomelodies\Stiletto;

use Monolyth\Cliff;
use Ansi;

class Command extends Cliff\Command
{
    public array $source = [];

    private array $files = [];

    public function __invoke(string $cssDir) : void
    {
        if (!$this->source) {
            $this->source = [$cssDir];
        }
        foreach ($this->source as $source) {
            $this->walkSourceDir($source);
        }
        $this->walkCssDir($cssDir);
    }

    private function walkSourceDir(string $dir) : void
    {
        $d = dir($dir);
        while (false !== ($entry = $d->read())) {
            if (in_array($entry, ['.', '..'])) {
                continue;
            }
            if (is_dir("$dir/$entry")) {
                $this->walkSourceDir("$dir/$entry");
            } elseif (preg_match("@\.(css|scss|less)$@", $entry)) {
                continue;
            } elseif (preg_match('@\.jsx?$@', $entry)) {
                $code = file_get_contents("$dir/$entry");
                preg_match_all('@[\.#][\w-]+@', $code, $mentions);
                $this->files["$dir/$entry"] = $mentions[0];
            } else {
                $code = file_get_contents("$dir/$entry");
                preg_match_all('@(id|class)="(.*?)"@', $code, $mentions);
                $this->files["$dir/$entry"] = [];
                foreach ($mentions[2] as $i => $mention) {
                    $parts = preg_split("@\s+@", $mention);
                    foreach ($parts as $selector) {
                        $this->files["$dir/$entry"][] = ($mentions[1][$i] == 'id' ? '#' : '.').$selector;
                    }
                }
            }
        }
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
                $lines = explode("\n", $css);
                foreach ($lines as $lineno => $line) {
                    $rules = [];
                    // If we're not opening a block of rules, ignore by default.
                    if (!preg_match("@{\$@", $line)) {
                        continue;
                    }
                    // Adjacent child etc. selectors are taking it too far.
                    $line = preg_replace('@[+~>&]@', ' ', $line);
                    $_rules = preg_split("@\s+@", trim(substr($line, 0, -1)));
                    array_walk($_rules, function (&$rule) use (&$rules) {
                        // Ignore pseudo-classes.
                        $rule = preg_replace('@:.*$@', '', $rule);
                        preg_match_all('@([\.#][\w-]+)@', $rule, $parts);
                        $rules = array_merge($rules, $parts[0]);
                    });
                    $rules = array_filter(array_unique($rules), function ($rule) {
                        // Ignore empty selectors, or any selectors that do not contain an id or class name.
                        return strlen($rule) && (strpos($rule, '.') !== false || strpos($rule, '#') !== false);
                    });
                    foreach ($rules as $rule) {
                        foreach ($this->files as $file) {
                            if (in_array($rule, $file)) {
                                continue 2;
                            }
                        }
                        echo Ansi::tagsToColors("In <red>$dir/$entry<reset>: <green>$rule<reset> on line <bold>$lineno<reset>\n");
                    }
                }
            }
        }
    }

    private function stripType(string &$rule) : void
    {
        $rule = substr($rule, 1);
    }
}

