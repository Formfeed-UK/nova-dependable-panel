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
        //dump($data);

        foreach ($data->resource->fields as $fieldKey => $field) {
            if (isset($field->component) && $field->component === 'nova-dependable-panel') {
                $data->resource->fields = [...$data->resource->fields, ...$field->fields];
                unset($data->resource->fields[$fieldKey]);
            }
        }
        return $data;
    }

    protected function handleDetail($data) {
        foreach ($data->panels as $panelKey => $panel) {
            foreach ($panel->fields as $fieldKey => $field) {
                if (isset($field->component) && $field->component === 'nova-dependable-panel') {
                    $panel->fields = [...$panel->fields, ...$field->fields];
                    unset($panel->fields[$fieldKey]);
                }
            }
        }

        foreach ($data->resource->fields as $fieldKey => $field) {
            if (isset($field->component) && $field->component === 'nova-dependable-panel') {
                $data->resource->fields = [...$data->resource->fields, ...$field->fields];
                unset($data->resource->fields[$fieldKey]);
            }
        }

        return $data;
    }

    protected function handleIndex($data) {
        foreach ($data->resources as $resourceKey => $resource) {
            foreach ($resource->fields as $fieldKey => $field) {
                if (isset($field->component) && $field->component === 'nova-dependable-panel') {
                    $resource->fields = [...$resource->fields, ...$field->fields];
                    unset($resource->fields[$fieldKey]);
                }
            }
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
