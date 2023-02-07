<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Exception;

use Symfony\Contracts\HttpClient\ResponseInterface;

final class TypesenseException extends \RuntimeException
{
    public $status;

    /**
     * @var string
     */
    public $message;

    public function __construct($message = "", $code = 0, $previous = null)
    {
        if($message instanceof ResponseInterface) {

            $response = $message;
            $this->status  = $response->getStatusCode();
            $this->message = json_decode($response->getContent(false), true)['message'] ?? '';
        }

        parent::__construct($message, $code, $previous);
    }
}
