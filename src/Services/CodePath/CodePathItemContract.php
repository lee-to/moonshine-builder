<?php

namespace DevLnk\MoonShineBuilder\Services\CodePath;

interface CodePathItemContract
{
    public function name(): string;

    public function rawName(): string;

    public function dir(): string;

    public function file(): string;

    public function namespace(): string;
}
