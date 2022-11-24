<?php

namespace Formfeed\DependablePanel\Http\Middleware;

use ArrayObject;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Str;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Controllers\UpdateFieldController;
use Laravel\Nova\Http\Controllers\CreationFieldController;
use Laravel\Nova\Http\Controllers\CreationFieldSyncController;
use Laravel\Nova\Http\Controllers\UpdatePivotFieldController;
use Laravel\Nova\Http\Controllers\CreationPivotFieldController;
use Laravel\Nova\Http\Requests\NovaRequest;

class InterceptDependentFields {

    /**
     * Handle the given request and get the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response {

        if (array_key_exists("uses", $request->route()->action) && $request->route()->action['uses'] instanceof Closure) {
            return $next($request);
        }

        if (!$this->isDependentFieldRequest($request)) {
            return $next($request);
        }

        if (!$this->hasDependentPanelFields($request)) {
            return $next($request);
        }

        $dependentPanel = $this->getDependentPanel($request);
        $componentKey = $this->getFieldComponentKey($request);

        $response = $next($request);
        if ($response instanceof JsonResponse) {
            $content = $response->getOriginalContent();
            if ($content instanceof ArrayObject && $content->count() === 0) {
                $response = response()->json(
                    $this->getDependentPanelFields($request, $dependentPanel, $componentKey)
                );
            }
        }
        return $response;
    }

    protected function isDependentFieldRequest(Request $request) {
        if (!$request->isMethod("PATCH")) {
            return false;
        }
        return (is_null($this->getFieldMethod($request))) ? false : true;
    }

    protected function hasDependentPanelFields(Request $request) {
        if (!$request->has("component")) {
            return false;
        }
        if (Str::contains($request->get("component"), "dependent_panel")) {
            return true;
        }
        return false;
    }

    protected function getDependentPanel(Request $request) {
        $parts = explode(".", $request->get("component"));
        return $parts[1];
    }

    protected function getFieldComponentKey(Request $request) {
        $parts = explode(".", $request->get("component"));
        return "{$parts[2]}.{$parts[3]}.{$parts[4]}";
    }

    protected function getDependentPanelFields(Request $request, string $panel, string $componentKey) {
        $request =  NovaRequest::createFrom($request);
        $fieldMethod = $this->getFieldMethod($request);
        $panel = $request->newResource()
            ->$fieldMethod($request)
            ->filter(function ($field) use ($panel) {
                return $panel === $field->attribute;
            })->first();
        $fields = $panel?->fields ?? [];
        $fields = (new FieldCollection($fields))
            ->filter(function ($field) use ($componentKey, $request) {
                return $request->query('field') === $field->attribute &&
                    $componentKey === $field->dependentComponentKey();
            })
            ->each->syncDependsOn($request)->first();
        return $fields;
    }

    protected function getFieldMethod(Request $request) {
        $routeController = $request->route()->getController();
        switch (get_class($routeController)) {
            case UpdateFieldController::class:
                return "updateFields";
            case UpdatePivotFieldController::class:
                return "updatePivotFields";
            case CreationFieldController::class:
            case CreationFieldSyncController::class:
                return "creationFields";
            case CreationPivotFieldController::class:
                return "creationPivotFields";
        }
    }
}
