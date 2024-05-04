<?php

namespace Formfeed\DependablePanel\Http\Middleware;

use ArrayObject;
use Closure;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Controllers\UpdateFieldController;
use Laravel\Nova\Http\Controllers\CreationFieldController;
use Laravel\Nova\Http\Controllers\CreationFieldSyncController;
use Laravel\Nova\Http\Controllers\UpdatePivotFieldController;
use Laravel\Nova\Http\Controllers\CreationPivotFieldController;
use Laravel\Nova\Http\Requests\NovaRequest;

use Laravel\Nova\Http\Resources\UpdateViewResource;
use Laravel\Nova\Http\Resources\UpdatePivotFieldResource;
use Laravel\Nova\Http\Resources\CreateViewResource;
use Laravel\Nova\Http\Resources\CreationPivotFieldResource;
use Laravel\Nova\Http\Resources\ReplicateViewResource;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\ResourceCreateOrAttachRequest;
use Laravel\Nova\Http\Requests\ResourceUpdateOrUpdateAttachedRequest;

class InterceptSubfieldDependsOn
{
    /**
     * Handle the given request and get the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response {

        // Pass through if closure, getController is unsafe to use with closures, if its a closure it's not a Nova DependsOn request
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

            $field = [];

            // If the Field is successfully resolved via another middleware or resource method, just use that
            if ($content instanceof Field) {
                $field = $content;
            }

            // If the Field is not resolved, try to find it and sync depends on
            if ($content instanceof ArrayObject && $content->count() === 0) {
                $field = $this->findField($novaRequest, $request->input("component"));
                if ($field instanceof Field) {
                    $field->syncDependsOn($novaRequest);
                }
            }

            // If there are additional actions needed after dependsOn sync, run them
            if (method_exists($field, "afterDependsOnSync")) {
                $field = $field->afterDependsOnSync($novaRequest) ?? $field;
            }

            $response = response()->json($field);
        }

        return $response;
    }

    protected function isDependentFieldRequest(Request $request) {
        return ($request->isMethod("PATCH") && $this->isNovaFieldController($request));
    }

    protected function findField(NovaRequest $request, string $componentKey) {
        $fields = $this->getResourceFields($request);
        return $this->findNestedField($request, $fields, $componentKey);
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
        return $this->getFieldsForRequest($request);
    }

    protected function resourceHasSubfields(NovaRequest $request): bool {
        return $request->newResource()->availableFields($request)->contains(function ($field) {
            return method_exists($field, "getSubfields") && method_exists($field, "hasSubfields");
        });
    }

    protected function isNovaFieldController(Request $request) {
        $routeController = $request->route()->getController();
        return in_array(get_class($routeController), [
            UpdateFieldController::class,
            UpdatePivotFieldController::class,
            CreationFieldController::class,
            CreationFieldSyncController::class,
            CreationPivotFieldController::class,
        ]);
    }

    protected function getFieldsForRequest(NovaRequest $request) {
        $routeController = $request->route()->getController();
        switch (get_class($routeController)) {
            case UpdateFieldController::class: {
                $resource = UpdateViewResource::make()->newResourceWith(ResourceUpdateOrUpdateAttachedRequest::createFrom($request));
                return $resource->creationFieldsWithinPanels($request);
            }
            case UpdatePivotFieldController::class: {
                $resource = UpdatePivotFieldResource::make()->newResourceWith(ResourceUpdateOrUpdateAttachedRequest::createFrom($request));
                return $resource->updatePivotFields($request, $request->relatedResource);
            }
            case CreationFieldController::class:
            case CreationFieldSyncController::class: {
                $resource = $request->has('fromResourceId')
                    ? ReplicateViewResource::make($request->fromResourceId)->newResourceWith(ResourceCreateOrAttachRequest::createFrom($request))
                    : CreateViewResource::make()->newResourceWith(ResourceCreateOrAttachRequest::createFrom($request));
                return $resource->updateFieldsWithinPanels($request);
            }
            case CreationPivotFieldController::class: {
                $resource = CreationPivotFieldResource::make()->newResourceWith(ResourceCreateOrAttachRequest::createFrom($request));
                return $resource->creationPivotFields($request, $request->relatedResource);
            }
            default: {
                return FieldCollection::make([]);
            }
        }
    }
}