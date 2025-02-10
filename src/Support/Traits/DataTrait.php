<?php

namespace DevLnk\MoonShineBuilder\Support\Traits;

trait DataTrait
{
    /**
     * @var array
     */
    private array $data = [];

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setDataValue(mixed $key, mixed $value): void
    {
        if(isset($this->data[$key])) {
            return;
        }
        $this->data[$key] = $value;
    }

    /**
     * @return array
     */
    public function data(): array
    {
        return $this->data;
    }

    public function dataValue(mixed $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
