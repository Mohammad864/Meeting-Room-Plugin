<?php

namespace MRB\Models;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Room domain model / data-transfer object.
 */
class Room
{
    public int    $id        = 0;
    public string $name      = '';
    public string $createdAt = '';

    public static function fromArray(array $data): self
    {
        $model = new self();

        $model->id        = isset($data['id'])         ? (int) $data['id']           : 0;
        $model->name      = isset($data['name'])        ? (string) $data['name']      : '';
        $model->createdAt = isset($data['created_at'])  ? (string) $data['created_at'] : '';

        return $model;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'created_at' => $this->createdAt,
        ];
    }
}
