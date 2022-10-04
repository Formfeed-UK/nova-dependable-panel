<?php

namespace Formfeed\DependablePanel\Http\Middleware;

use ArrayObject;
use Closure;
use Formfeed\DependablePanel\DependablePanel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Controllers\ResourceUpdateController;
use Laravel\Nova\Http\Controllers\ResourceStoreController;
use Laravel\Nova\Http\Requests\NovaRequest;

class InterceptValidationFailure {

    /**
     * Handle the given request and get the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response {

        if (!$this->isDependentFieldRequest($request)) {
            return $next($request);
        }

        if (!$this->hasDependentPanelFields($request)) {
            return $next($request);
        }

        $response = $next($request);

        if (!$this->hasValidationFail($response)) {
            return $response;
        }

        $exceptionFields = $this->getValidationFailFields($response);

        $exceptions = $this->parseDependableExeptions($request, $exceptionFields);

        if (count($exceptions) === 0) {
            return $response;
        }
        
        $exceptionFields = [...$response->exception->errors(), ...$exceptions];

        $exception = ValidationException::withMessages($exceptionFields);

        return response()->json([
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    protected function isDependentFieldRequest(Request $request) {
        $routeController = $request->route()->getController();
        if ($routeController && ($routeController instanceof ResourceUpdateController ||
            $routeController instanceof ResourceStoreController
        )) {
            return true;
        }
        return false;
    }

    protected function hasDependentPanelFields(Request $request) {
        if ($request->has("_dependent_field") && $request->get("_dependent_field") == "true") {
            return true;
        }
        return false;
    }

    protected function getDependentPanels(Request $request) {
        $request =  NovaRequest::createFrom($request);
        $fieldMethod = $this->getFieldMethod($request);
        return $request->newResource()
            ->$fieldMethod($request)
            ->filter(function ($field) {
                return $field instanceof DependablePanel;
            });
    }

    protected function parseDependableExeptions($request, $exceptionFields) {
        $panels = $this->getDependentPanels($request);
        $panelErrors = [];
        $panels->each(function ($panel) use ($exceptionFields, &$panelErrors) {
            collect($panel->fields)->each(function ($field) use ($exceptionFields, $panel, &$panelErrors) {
                if (in_array($field->attribute, $exceptionFields)) {
                    $panelErrors[$panel->attribute] = ["error"];
                }
            });
        });
        return $panelErrors;
    }

    protected function hasValidationFail($response) {
        if ($response->exception && $response->exception instanceof ValidationException) {
            return true;
        }
        return false;
    }

    protected function getValidationFailFields($response) {
        return array_keys($response->exception->errors());
    }

    protected function getFieldMethod(Request $request) {
        $routeController = $request->route()->getController();
        switch (get_class($routeController)) {
            case ResourceUpdateController::class:
                return "updateFields";
            case AttachedResourceUpdateController::class:
                return "updatePivotFields";
            case ResourceStoreController::class:
                return "creationFields";
            case ResourceAttachController::class:
                return "creationPivotFields";
            default:
                return "creationFields";
        }
    }
}
