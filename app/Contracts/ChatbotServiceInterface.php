<?php

namespace App\Contracts;

interface ChatbotServiceInterface
{
    /**
     * Gửi chuỗi prompt đến AI và nhận văn bản phản hồi.
     *
     * @param string $prompt
     * @return string|null
     */
    public function sendMessage(string $prompt): ?string;
}