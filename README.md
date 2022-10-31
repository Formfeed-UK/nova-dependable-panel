# Nova 4 Dependable Panel

This [Laravel Nova](https://nova.laravel.com/) package adds Panels that allow grouped dependsOn functionality

Dusk Test Repo: https://github.com/Formfeed-UK/nova-dependable-panel-tests 

## Requirements

- `php: ^7.3|^8.0`
- `laravel/nova: ^4.0`

## Features

- Grouping all dependsOn requests for fields within this panel into one request
- Hiding/Showing the entire Panel based on a dependsOn function
- Changing the Fields within the Panel as a result of a dependsOn function
- Making a batched dependsOn function for all fields within the panel, (overidden at the Field Level)
- Works with Nova Tabs
- Works with the fork of Nova Flexible Content allowing for dependsOn fields within Layout Groups
- Fields can be added to an existing panel or seperated into a new one

Note that this package is an extension of a Field, rather than a Panel, however it can act as a panel when the `separatePanel` method is used. This is to allow more flexibility to use fields as part of an existing Panel. 

## Installation

Install the package in to a Laravel app that uses [Nova](https://nova.laravel.com) via composer:

```bash
composer require formfeed-uk/nova-dependable-panel
```

## Usage

### Basic Usage

#### Hiding the entire Panel based on a dependsOn function

```php
    use FormFeed\NovaDependablePanel\DependablePanel;

    public function fields(NovaRequest $request)
    {
        return [
            Select::make('Select', 'select')->options([
                'option1' => 'Option 1',
                'option2' => 'Option 2',
            ]),
            DependablePanel::make('Panel Title', [
                Text::make('Field 1'),
                Text::make('Field 2'),
            ])
            ->dependsOn(["select"], function (DependablePanel $panel, NovaRequest $request, FormData $formData) {
                if ($formData['select'] == "option1") {
                    $panel->hide();
                }
            }),
        ];
    }
```

#### Single dependsOn request for all fields within the panel
All of the Text Fields dependsOn requests will be sent as one request using the `singleRequest` method. This is useful for both reducing the number of requests on large forms and for more consistent UX as the fields are changed at the same time. 

```php
    use FormFeed\NovaDependablePanel\DependablePanel;

    public function fields(NovaRequest $request)
    {
        return [
            DependablePanel::make('Panel Title', [
                Select::make('Select', 'select')->options([
                    'option1' => 'Option 1',
                    'option2' => 'Option 2',
                ]),
                Text::make('Hide Field 1')
                    ->dependsOn(["select"], function (Text $field, NovaRequest $request, FormData $formData) {
                        if ($formData['select'] == "option1") {
                            $field->hide();
                        }
                    }),
                Text::make('Hide Field 2')
                    ->dependsOn(["select"], function (Text $field, NovaRequest $request, FormData $formData) {
                        if ($formData['select'] == "option1") {
                            $field->hide();
                        }
                    }),
                Text::make('Show Field 3')
                    ->hide()
                    ->dependsOn(["select"], function (Text $field, NovaRequest $request, FormData $formData) {
                        if ($formData['select'] == "option1") {
                            $field->show();
                        }
                    }),
                Text::make('Show Field 4')
                    ->hide()
                    ->dependsOn(["select"], function (Text $field, NovaRequest $request, FormData $formData) {
                        if ($formData['select'] == "option1") {
                            $field->show();
                        }
                    }),
            ])
            ->singleRequest(true);
        ];
    }
```

#### Batching dependsOn functionality for all contained fields
You can also batch dependsOn functionality for all fields within your Panel using the `applyToFields` method. For example if you need all fields to become readOnly. This can be overidden at the Field Level.

Note that the first parameter should be of type `Field` to ensure that the function can apply to all fields within your panel, regardless of type. 

```php
    use FormFeed\NovaDependablePanel\DependablePanel;

    public function fields(NovaRequest $request)
    {
        return [
            DependablePanel::make('Panel Title', [
                Select::make('Select', 'select')->options([
                    'option1' => 'Option 1',
                    'option2' => 'Option 2',
                ]),
                Text::make('Field 1'),
                Text::make('Field 2'),
                Text::make('Field 3')
                ->dependsOn(['select'], function (Text $field, NovaRequest $request, FormData $formData) {
                    if ($formData['select'] == "option1") {
                        $field->readOnly(false);
                    }
                })
            ])
            ->dependsOn(["select"], function (DependablePanel $panel, NovaRequest $request, FormData $formData) {
                $panel->applyToFields(function (Field $field, NovaRequest $request, FormData $formData) {
                    if ($formData['select'] == "option1") {
                        $field->readOnly();
                    }
                });
            }),
        ];
    }
```

#### Separating the Panel
The fields in Nova Dependable Panel can be part of any other panel (default), or can be separated into another panel using the `separatePanel` method.

This panel will use the first argument to DependablePanel as its panel name.

```php
    use FormFeed\NovaDependablePanel\DependablePanel;

    public function fields(NovaRequest $request)
    {
        return [
            DependablePanel::make('Panel Title', [
                Select::make('Select', 'select')->options([
                    'option1' => 'Option 1',
                    'option2' => 'Option 2',
                ]),
                Text::make('Field 1'),
                Text::make('Field 2'),
                Text::make('Field 3')
            ])
            ->separatePanel(true)   
        ];
    }
```

#### Changing the fields in the Panel as a result of dependsOn 
Rather than hiding/showing fields as a result of dependsOn, you can change the fields themselves using the `fields` method. Note that this functionality requires `singleRequest` to operate properly. 

```php
    use FormFeed\NovaDependablePanel\DependablePanel;

    public function fields(NovaRequest $request)
    {
        return [
            Select::make('Select', 'select')->options([
                'option1' => 'Option 1',
                'option2' => 'Option 2',
            ]),
            DependablePanel::make('Panel Title', [
                Text::make('Field 1'),
                Text::make('Field 2'),
            ])
            ->singleRequest(true)
            ->dependsOn(["select"], function (DependablePanel $panel, NovaRequest $request, FormData $formData) {
                if ($formData['select'] == "option1") {
                    $panel->fields([
                        Text::make('Field 3'),
                        Text::make('Field 4'),
                    ]);
                }
            }),
        ];
    }
```

**Note: If the fields added via the `fields` method also have dependsOn defined, and you wish those dependsOn mixins to be applied correctly when those fields are first mounted, you should include the fields that they depend upon in the panels `dependsOn` function.**

In the example below if the "Readonly" checkbox is checked, and then the select option is changed to Option 1, in order for these fields to be readonly when mounted, you can see "readonly" needs to be included in the panels `dependsOn` function.

```php
    use FormFeed\NovaDependablePanel\DependablePanel;

    public function fields(NovaRequest $request)
    {
        return [
            Select::make('Select', 'select')->options([
                'option1' => 'Option 1',
                'option2' => 'Option 2',
            ]),
            Boolean::make('Readonly'),
            DependablePanel::make('Panel Title', [
                Text::make('Field 1'),
                Text::make('Field 2'),
            ])
            ->singleRequest(true)
            ->dependsOn(["select", "readonly"], function (DependablePanel $panel, NovaRequest $request, FormData $formData) {
                if ($formData['select'] == "option1") {
                    $panel->fields([
                        Text::make('Field 3')
                            ->dependsOn(["readonly"], function (Text $field, NovaRequest $request, FormData $formData) {
                                if ($formData['readonly']) {
                                    $field->readonly();
                                }
                            }),
                        Text::make('Field 4')
                            ->dependsOn(["readonly"], function (Text $field, NovaRequest $request, FormData $formData) {
                                if ($formData['readonly']) {
                                    $field->readonly();
                                }
                            }),
                    ]);
                }
            }),
        ];
    }
```

## Known Issues
- Panels cannot be seperated/combined in the dependsOn function, this can only be set once upon page load
- When swapping between different sets of fields via the `Fields` method in the Panels `dependsOn` changed values will not persist on the update form for fields where values are already stored in the database for that resource.
- Nova's Boolean field doesn't behave as expected due to its value being set to false when it is null, this means that Boolean fields altered by the dependable panel will be false, which isn't great UX. A middleware fix is incoming, but for now it's recommended to extend the Boolean field within your project and perform the following override:

```php
    protected function resolveDefaultValue(NovaRequest $request)
    {
        return Field::resolveDefaultValue($request);
    }
```

## License

Nova Dependable Panel is open-sourced software licensed under the [MIT license](LICENSE.md).


