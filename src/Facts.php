<?php

namespace Kennyth01\PhpRulesEngine;

use Exception;

class Facts
{
    private array $facts = [];

    public function add(string $factName, $value): void
    {
        $this->facts[$factName] = $value;
    }

    public function get(string $factName, ?string $path = null)
    {
        if (!isset($this->facts[$factName])) {
            return null;
        }

        $factData = $this->facts[$factName];

        // Handle nested paths like $.profile.isHidden
        if ($path) {
            $keys = explode('.', ltrim($path, '$.'));
            foreach ($keys as $key) {
                if (!array_key_exists($key, $factData)) {
                    throw new Exception("Path '$path' not found in fact '$factName'");
                }
                $factData = $factData[$key];
            }
        }

        return $factData;
    }

    public function getAll(): array
    {
        return $this->facts;
    }
}
