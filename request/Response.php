<?php

declare(strict_types=1);

class Response
{
    public int $status;
    public array $headers;
    public string $body;

    public function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function json(bool $assoc = true)
    {
        return json_decode($this->body, $assoc);
    }
}
