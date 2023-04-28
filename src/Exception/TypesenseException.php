<?php

declare(strict_types=1);

namespace Typesense\Bundle\Exception;

use Http\Client\Exception\NetworkException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Typesense\Exceptions\HTTPStatus0Error;
use Typesense\Exceptions\ObjectAlreadyExists;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\ObjectUnprocessable;
use Typesense\Exceptions\RequestMalformed;
use Typesense\Exceptions\RequestUnauthorized;
use Typesense\Exceptions\ServerError;
use Typesense\Exceptions\ServiceUnavailable;

final class TypesenseException extends \RuntimeException
{
    public $status;

    /**
     * @var string
     */
    public $message;

    public function __construct($message = "", $code = 0, $previous = null)
    {
        if ($message instanceof ResponseInterface) {

            $response = $message;
            $this->status  = $response->getStatusCode();
            $this->message = json_decode($response->getContent(false), true)['message'] ?? '';
        }

        if($code == 0) $code = $this->transformExceptionToStatusCode($previous);
        parent::__construct($message, $code, $previous);
    }

    private function transformExceptionToStatusCode($e)
    {
        if($e instanceof RequestMalformed) return 400;
        if($e instanceof RequestUnauthorized) return 401;
        if($e instanceof ObjectNotFound) return 404;
        if($e instanceof ObjectAlreadyExists) return 409;
        if($e instanceof ObjectUnprocessable) return 422;
        if($e instanceof ServerError) return 500;
        if($e instanceof ServiceUnavailable) return 503;
        if($e instanceof NetworkException) return 503;

        return 0;
    }
}
