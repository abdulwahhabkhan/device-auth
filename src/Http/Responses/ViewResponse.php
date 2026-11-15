<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Responses;

use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\ConfirmViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceIndexViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceShowViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\VerifyViewResponse;
use AbdulWahhabKhan\DeviceAuth\Data\PageData;
use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Renders a device-auth page from a view registered with DeviceAuth::*View().
 * Without a view the page data is returned as JSON (headless mode).
 */
final readonly class ViewResponse implements ConfirmViewResponse, DeviceIndexViewResponse, DeviceShowViewResponse, VerifyViewResponse
{
    /**
     * @param  (Closure(Request, PageData): mixed)|view-string|null  $view
     */
    public function __construct(private Closure|string|null $view = null) {}

    public function toResponse(Request $request, PageData $data): Response
    {
        if ($this->view === null) {
            return new JsonResponse($data->toArray());
        }

        $result = is_string($this->view)
            ? view($this->view, ['data' => $data, ...$data->toArray()])
            : ($this->view)($request, $data);

        return match (true) {
            $result instanceof Response => $result,
            $result instanceof Responsable => $this->symfony($result->toResponse($request)),
            $result instanceof View, is_string($result) => new IlluminateResponse($result),
            default => throw new UnexpectedValueException('A device-auth view must return a response, a view or a string.'),
        };
    }

    private function symfony(mixed $response): Response
    {
        if (! $response instanceof Response) {
            throw new UnexpectedValueException('A Responsable device-auth view must produce a Symfony response.');
        }

        return $response;
    }
}
