<?php

namespace Formfeed\DependablePanel\Http\Middleware;

use ArrayObject;
use Closure;
use Formfeed\DependablePanel\DependablePanel;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Laravel\Nova\Http\Controllers\ResourceIndexController;
use Laravel\Nova\Http\Controllers\ResourceShowController;
use Laravel\Nova\Http\Controllers\ResourcePreviewController;
use Laravel\Nova\Http\Requests\NovaRequest;

class InterceptDisplayFields {

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

        $routeController = $request->route()->getController();
        if (!$this->isDisplayRequest($routeController)) {
            return $next($request);
        }

        $response = $next($request);

        $data = $response->getData();
        $type = $this->requestType($routeController);

        switch ($type) {
            case 'index':
                $data = $this->handleIndex($data);
                break;
            case 'preview':
                $data = $this->handlePreview($data);
                break;
        }
        $response->setData($data);
        return $response;
    }

    protected function handlePreview($data) {
        if (isset($data->resource?->fields)) {
            foreach ($data->resource->fields as $fieldKey => $field) {
                if (isset($field->component) && $field->component === 'nova-dependable-panel') {
                    $data->resource->fields = [...$data->resource->fields, ...$field->fields];
                }
                $data->resource->fields = array_filter($data->resource->fields, function ($field) {
                    return $field->component !== 'nova-dependable-panel';
                });
            }
        }
        return $data;
    }

    protected function handleDetail($data) {
        if (isset($data->panels)) {
            foreach ($data->panels as $panelKey => $panel) {
                foreach ($panel->fields as $fieldKey => $field) {
                    if (isset($field->component) && $field->component === 'nova-dependable-panel') {
                        $panel->fields = [...$panel->fields, ...$field->fields];
                    }
                }
                $panel->fields = array_filter($panel->fields, function ($field) {
                    return $field->component !== 'nova-dependable-panel';
                });
            }
        }

        if (isset($data->resource?->fields)) {
            foreach ($data->resource->fields as $fieldKey => $field) {
                if (isset($field->component) && $field->component === 'nova-dependable-panel') {
                    $data->resource->fields = [...$data->resource->fields, ...$field->fields];
                }
                $data->resource->fields = array_filter($data->resource->fields, function ($field) {
                    return $field->component !== 'nova-dependable-panel';
                });
            }
        }

        return $data;
    }

    protected function handleIndex($data) {
        if (!isset($data->resources)) {
            return $data;
        }
        foreach ($data->resources as $resourceKey => $resource) {
            foreach ($resource->fields as $fieldKey => $field) {
                if (isset($field->component) && $field->component === 'nova-dependable-panel') {
                    $resource->fields = [...$resource->fields, ...$field->fields];
                }
            }
            $resource->fields = array_filter($resource->fields, function ($field) {
                return $field->component !== 'nova-dependable-panel';
            });
        }
        return $data;
    }

    protected function isDisplayRequest($routeController) {
        return ($routeController instanceof ResourceIndexController ||
            $routeController instanceof ResourceShowController ||
            $routeController instanceof ResourcePreviewController
        ) ? true : false;
    }

    protected function requestType($routeController) {
        if ($routeController instanceof ResourceIndexController) {
            return 'index';
        }
        if ($routeController instanceof ResourceShowController) {
            return 'detail';
        }
        if ($routeController instanceof ResourcePreviewController) {
            return 'preview';
        }
        return false;
    }

    protected function getDependentPanels(Request $request) {
        $request =  NovaRequest::createFrom($request);
        return $request->newResource()
            ->availableFields($request)
            ->filter(function ($field) {
                return $field instanceof DependablePanel;
            });
    }
}
