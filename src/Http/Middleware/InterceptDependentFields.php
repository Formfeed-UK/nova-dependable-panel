<?php

namespace Formfeed\DependablePanel\Http\Middleware;

use ArrayObject;
use Closure;
use Formfeed\DependablePanel\DependablePanel;
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

use Formfeed\NovaFlexibleContent\Flexible as FormfeedFlexible;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Field;
use Whitecube\NovaFlexibleContent\Flexible as WhitecubeFlexible;

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

        $novaRequest = NovaRequest::createFrom($request);

        if (!$this->isDependentFieldRequest($request)) {
            return $next($request);
        }

        
        if (!$this->resourceHasSubfields($novaRequest)) {
            return $next($request);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $content = $response->getOriginalContent();
            if ($content instanceof ArrayObject && $content->count() === 0) {
                $field = $this->findField($novaRequest, $request->input("component"), $request->input("_viaField"));
                if ($field instanceof Field) {
                    $response = response()->json($field->syncDependsOn($novaRequest));
                }
                else {
                    $response = response()->json();
                }
            }
        }

        // Ensure any flexible content fields within dependant panel are resolved
        if (Str::contains($request->get("component"), "nova-dependable-panel")) {
            $panel = $response->getOriginalContent();
            if ($panel instanceof DependablePanel) {
                foreach ($panel->fields as $field) {
                    if ((class_exists(FormfeedFlexible::class) && $field instanceof FormfeedFlexible) || (class_exists(WhitecubeFlexible::class) && $field instanceof WhitecubeFlexible)) {
                        $field->resolve($novaRequest->newResource(), $field->attribute);
                        $field->value = ($field->value instanceof Collection && $field->value->count() > 0) ? $field->value : null;
                    }
                }
                return response()->json($panel);
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

    protected function findField(NovaRequest $request, string $componentKey, string $viaField) {
        $fields = $this->getResourceFields($request);
        return $this->findNestedField($request, $fields, $componentKey, $viaField);
    }

    protected function findNestedField(NovaRequest $request, FieldCollection $fields, string $componentKey) {

        if ($fields->count() === 0) {
            return [];
        }

        return $fields->first(fn ($field) => ($field->dependentComponentKey() === $componentKey)) ?? $this->findNestedField($request, $this->getSubfields($request, $fields), $componentKey);
    }

    protected function getSubfields(NovaRequest $request, FieldCollection $fields) {
        return rescue(function () use ($fields, $request) {
            return $fields->filter(function ($field) use ($request) {
                return $this->fieldHasSubfields($request, $field);
            })->map(function ($field) use ($request) {
                return $field->getSubfields($request);
            })->flatten();
        }, FieldCollection::make([]), false);
    }

    protected function fieldHasSubfields(NovaRequest $request, Field $field): bool {
        return method_exists($field, "getSubfields") && method_exists($field, "hasSubfields") && $field->hasSubfields($request);
    }

    protected function getResourceFields(NovaRequest $request): FieldCollection {
        $fieldMethod = $this->getFieldMethod($request);
        return $request->newResource()
            ->$fieldMethod($request);
    }

    protected function resourceHasSubfields(NovaRequest $request): bool {
        return $this->getResourceFields($request)->contains(function ($field) {
            return method_exists($field, "getSubfields") && method_exists($field, "hasSubfields");
        });
    }

    protected function getFieldMethod(Request $request) {
        $routeController = $request->route()->getController();
        switch (get_class($routeController)) {
            case UpdateFieldController::class:
                return "updateFieldsWithinPanels";
            case UpdatePivotFieldController::class:
                return "updatePivotFields";
            case CreationFieldController::class:
            case CreationFieldSyncController::class:
                return "creationFieldsWithinPanels";
            case CreationPivotFieldController::class:
                return "creationPivotFields";
        }
    }
}
