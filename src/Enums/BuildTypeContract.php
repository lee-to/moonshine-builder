<?php

namespace DevLnk\MoonShineBuilder\Enums;

interface BuildTypeContract
{
    public function value(): string;

    public function stub(): string;
}
