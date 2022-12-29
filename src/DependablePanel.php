<?php

namespace Formfeed\DependablePanel;

use Formfeed\NovaFlexibleContent\Http\FlexibleAttribute;
use Formfeed\NovaFlexibleContent\Http\ScopedRequest;
use Illuminate\Support\Str;


use Laravel\Nova\Contracts\ListableField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\SupportsDependentFields;
use Laravel\Nova\Http\Controllers\ResourcePreviewController;
use Laravel\Nova\Panel;
use Laravel\Nova\ResourceTool;
use Laravel\Nova\ResourceToolElement;

use Formfeed\NovaFlexibleContent\Flexible as FormfeedFlexible;
use Illuminate\Support\Collection;
use Whitecube\NovaFlexibleContent\Flexible as WhitecubeFlexible;

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
    }

    public function getUpdateRules(NovaRequest $request) {
        if ($request instanceof ScopedRequest && class_exists(FlexibleAttribute::class)) {
            $rules = [];
            foreach ($this->fields as $field) {
                $field->applyDependsOn($request);
                $fieldRules = $field->getUpdateRules($request);

                // Parse nested Flexible Attributes for when panel is nested within another Flexible Layout
                foreach ($fieldRules as $key => $rule) {
                    if (is_array($rule) && is_a($rule['attribute'] ?? null, FlexibleAttribute::class)) {
                        if (!(explode(".", $key)[0] === $field->attribute)) {
                            continue;
                        }
                        $rules[$key] = $rule;
                        unset($fieldRules[$key]);
                    }
                }

                if (is_a($fieldRules['attribute'] ?? null, FlexibleAttribute::class)) {
                    $rules = array_merge($rules, $fieldRules);
                } else {
                    $rules = array_merge($rules, [
                        $field->attribute => [
                            "attribute" => FlexibleAttribute::make($field->attribute, $request->group),
                            "rules" => $fieldRules
                        ]
                    ]);
                }
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
        if ($this->singleRequest || !$request->isMethod("PATCH")) {
            if (!$request->isMethod("PATCH") && !$request->isMethod("POST") && ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())) {
                $this->fields->applyDependsOnWithDefaultValues($request);
            } else {
                foreach ($this->fields as $field) {
                    $field->applyDependsOn($request);
                }
            }
        }
        return $this;
    }

    public function syncDependsOn(NovaRequest $request) {
        $this->value = null;
        $this->defaultCallback = null;
        // Using an ugly two loops as the value could be set via applyToFields in applyDependsOn
        foreach ($this->fields as $field) {
            $field->default(null);
            $field->value = null;
        }
        parent::applyDependsOn($request);
        foreach ($this->fields as $field) {
            $field->applyDependsOn($request);
        }

        return $this;
    }

    public function fields(array $fields) {
        $request = app(NovaRequest::class);
        $resource = $request->newResourceWith($request->findModel());
        $this->fields =  new FieldCollection($fields);
        $this->fields->resolve($resource);
        $this->fields->each(function ($field) use ($request) {
            if (is_null($field->value)) {
                $serialize = $field->jsonSerialize();
                $field->withMeta(["defaultValue" => $serialize['value']]);
                $field->default(null);
            }
        });
        $this->fields->applyDependsOnWithDefaultValues(NovaRequest::createFrom(request()));
        return $this;
    }

    public function getValidationAttributeNames(NovaRequest $request) {
        $this->applyDependsOn($request);
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
        $fields = $this->fields
            ->withoutReadonly($request)
            ->withoutUnfillable();
        foreach ($fields as $field) {
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

    public function hasSubfields(): bool {
        return true;
    }

    public function getSubfields(): FieldCollection {
        return $this->fields;
    }

    public function afterDependsOnSync(NovaRequest $request) : self {
        $this->fields->each(function ($field) use ($request) {
            if ((class_exists(FormfeedFlexible::class) && $field instanceof FormfeedFlexible) || (class_exists(WhitecubeFlexible::class) && $field instanceof WhitecubeFlexible)) {
                $field->resolve($request->newResource(), $field->attribute);
                $field->value = ($field->value instanceof Collection && $field->value->count() > 0) ? $field->value : null;
            }
        });

        return $this;
    }

    public function jsonSerialize(): array {
        $request = app(NovaRequest::class);
        return array_merge(parent::jsonSerialize(), [
            'fields' => $this->getFields($request)->jsonSerialize(),
            'singleRequest' => $this->singleRequest,
        ]);
    }
}
