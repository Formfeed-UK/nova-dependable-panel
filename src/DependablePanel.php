<?php

namespace Formfeed\DependablePanel;

use Formfeed\NovaFlexibleContent\Http\FlexibleAttribute;
use Formfeed\NovaFlexibleContent\Http\ScopedRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\SupportsDependentFields;
use Laravel\Nova\Http\Controllers\ResourcePreviewController;
use Laravel\Nova\Panel;

use PhpParser\Node\Expr\BinaryOp\BooleanAnd;

class DependablePanel extends Field {
    use SupportsDependentFields;

    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-dependable-panel';

    /**
     * The panel's fields
     *
     * @var FieldCollection
     */
    public $fields;

    public $singleRequest = false;

    protected $subFieldDependencies = [];

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|\Closure|callable|object|null  $attribute
     * @param  (callable(mixed, mixed, ?string):mixed)|null  $resolveCallback
     * @return void
     */
    public function __construct($name, array $fields = []) {
        $this->name = $name;
        $this->default(null);
        $this->attribute = str_replace(' ', '_', Str::lower($name));
        $this->fields = new FieldCollection($fields);

        $request = app(NovaRequest::class);
        if (count($this->fields->filterForPreview($request, $this->resource)) > 0) {
            $this->showOnPreview(true);
        }
    }

    public function __clone() {
        $this->fields = $this->fields->map(function ($field) {
            $field = clone $field;
            $field->applyDependsOn(NovaRequest::createFrom(request()));
            return $field;
        });
    }

    /**
     * Resolve the field's value.
     *
     * @param  mixed  $resource
     * @param  string|null  $attribute
     * @return void
     */
    public function resolve($resource, $attribute = null) {
        $this->resource = $resource;

        foreach ($this->fields as $field) {
            $field->resolve($resource, null);
        }

        $this->fields->applyDependsOnWithDefaultValues(NovaRequest::createFrom(request()));
    }

    public function getUpdateRules(NovaRequest $request) {
        if ($request instanceof ScopedRequest && class_exists(FlexibleAttribute::class)) {
            $rules = [];
            foreach ($this->fields as $field) {
                $field->applyDependsOn($request);
                $rules = array_merge($rules, [
                    $field->attribute => [
                        "attribute" => FlexibleAttribute::make($field->attribute, $request->group),
                        "rules" => $field->getUpdateRules($request)
                    ]
                ]);
            }
            return $rules;
        }
        $rules = [$this->attribute => []];
        foreach ($this->fields as $field) {
            $rules = array_merge($rules, $field->getUpdateRules($request));
        }
        return $rules;
    }

    public function getCreationRules(NovaRequest $request) {
        if ($request instanceof ScopedRequest && class_exists(FlexibleAttribute::class)) {
            $rules = [];
            foreach ($this->fields as $field) {
                $field->default(null);
                $field->applyDependsOn($request);
                $rules = array_merge($rules, [
                    $field->attribute => [
                        "attribute" => FlexibleAttribute::make($field->attribute, $request->group),
                        "rules" => $field->getCreationRules($request)
                    ]
                ]);
            }
            return $rules;
        }
        $rules = [$this->attribute => []];
        foreach ($this->fields as $field) {
            $rules = array_merge($rules, $field->getCreationRules($request));
        }
        return $rules;
    }

    public function applyDependsOn(NovaRequest $request) {
        parent::applyDependsOn($request);
        if ($this->singleRequest) {
            foreach ($this->fields as $field) {
                $field->applyDependsOn($request);
            }
        }
        return $this;
    }

    public function syncDependsOn(NovaRequest $request) {
        $this->value = null;
        $this->defaultCallback = null;
        parent::applyDependsOn($request);
        if ($this->singleRequest) {
            foreach ($this->fields as $field) {
                $field->default(null);
                $field->applyDependsOn($request);
            }
        }
        return $this;
    }

    public function fields(array $fields) {
        $this->fields = $fields;
        return $this;
    }

    public function getValidationAttributeNames(NovaRequest $request) {
        return array_merge(
            [$this->validationKey() => $this->name],
            $this->fields->mapWithKeys(function ($field) use ($request) {
                return $field->getValidationAttributeNames($request);
            })->all()
        );
    }

    public function separatePanel(bool $bool = true, $panelName = null) {

        if ($bool === false) {
            $this->panel = null;
            $this->assignedPanel = null;
            return $this;
        }

        $panelName ??= $this->name;
        $this->panel = $panelName;
        if ($this->assignedPanel && $this->assignedPanel instanceof Panel) {
            $this->assignedPanel->name = $panelName;
        } else {
            $this->assignedPanel = Panel::make($panelName, []);
        }
        return $this;
    }

    public function singleRequest($bool = true) {
        $this->singleRequest = $bool;
        return $this;
    }

    public function applyToFields($mixin) {
        $request = app(NovaRequest::class);
        $attributes = $this->getDependentsAttributes($request);
        $formData = FormData::onlyFrom($request, $attributes);
        foreach ($this->fields as $field) {
            $mixin($field, $request, $formData);
        }
        return $this;
    }

    public function fill(NovaRequest $request, $model) {
        foreach ($this->fields as $field) {
            $field->fillInto($request, $model, $field->attribute);
        }
    }

    public function getFields(NovaRequest $request) {
        if ($request->isCreateOrAttachRequest()) {
            return $this->fields->reject(function ($field) use ($request) {
                return $field instanceof ListableField ||
                    ($field instanceof ResourceTool || $field instanceof ResourceToolElement) ||
                    $field->attribute === 'ComputedField' ||
                    ($field instanceof ID && $field->attribute === $this->resource->getKeyName()) ||
                    !$field->isShownOnCreation($request);
            });
        } else if ($request->isUpdateOrUpdateAttachedRequest()) {
            return $this->fields->reject(function ($field) use ($request) {
                return $field instanceof ListableField ||
                    ($field instanceof ResourceTool || $field instanceof ResourceToolElement) ||
                    $field->attribute === 'ComputedField' ||
                    ($field instanceof ID && $field->attribute === $this->resource->getKeyName()) ||
                    !$field->isShownOnUpdate($request, $this->resource);
            });
        } else if ($request->isResourceIndexRequest()) {
            return $this->fields
                ->filterForIndex($request, $this->resource)
                ->withoutListableFields()
                ->authorized($request)
                ->resolveForDisplay($this->resource);
        } else if ($request->route()->getController() instanceof ResourcePreviewController) {
            return $this->fields
                ->filterForPreview($request, $this->resource)
                ->authorized($request)
                ->resolveForDisplay($this->resource);
        } else if ($request->isResourceDetailRequest()) {
            $test = $this->fields
                ->filterForDetail($request, $this->resource)
                ->authorized($request)
                ->resolveForDisplay($this->resource);
            return $test;
        } else {
            return $this->fields->authorized($request);
        }
    }

    public function jsonSerialize(): array {
        $request = app(NovaRequest::class);
        return array_merge(parent::jsonSerialize(), [
            'fields' => $this->getFields($request)->jsonSerialize(),
            'singleRequest' => $this->singleRequest,
        ]);
    }
}
