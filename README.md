# Nova 4 Dependable Panel

This [Laravel Nova](https://nova.laravel.com/) package adds Panels that allow grouped dependsOn functionality
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

    public function fields(Request $request)
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
            ->dependsOn(["select"], function (Fields\Field $field, NovaRequest $request, Fields\FormData $formData) {
                if ($formData['select'] == "option1") {
                    $field->hide();
                }
            }),
        ];
    }
```

#### Single dependsOn request for all fields within the panel
All of the Text Fields dependsOn requests will be sent as one request using the `singleRequest` method. This is useful for both reducing the number of requests on large forms and for more consistent UX as the fields are changed at the same time. 

```php
    use FormFeed\NovaDependablePanel\DependablePanel;

    public function fields(Request $request)
    {
        return [
            DependablePanel::make('Panel Title', [
                Select::make('Select', 'select')->options([
                    'option1' => 'Option 1',
                    'option2' => 'Option 2',
                ]),
                Text::make('Hide Field 1')
                    ->dependsOn(["select"], function (Fields\Field $field, NovaRequest $request, Fields\FormData $formData) {
                        if ($formData['select'] == "option1") {
                            $field->hide();
                        }
                    }),
                Text::make('Hide Field 2')
                    ->dependsOn(["select"], function (Fields\Field $field, NovaRequest $request, Fields\FormData $formData) {
                        if ($formData['select'] == "option1") {
                            $field->hide();
                        }
                    }),
                Text::make('Show Field 3')
                    ->hide()
                    ->dependsOn(["select"], function (Fields\Field $field, NovaRequest $request, Fields\FormData $formData) {
                        if ($formData['select'] == "option1") {
                            $field->show();
                        }
                    }),
                Text::make('Show Field 4')
                    ->hide()
                    ->dependsOn(["select"], function (Fields\Field $field, NovaRequest $request, Fields\FormData $formData) {
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

```php
    use FormFeed\NovaDependablePanel\DependablePanel;

    public function fields(Request $request)
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
                ->dependsOn(['select'], function (Fields\Field $field, NovaRequest $request, Fields\FormData $formData) {
                    if ($formData['select'] == "option1") {
                        $field->readOnly(false);
                    }
                })
            ])
            ->dependsOn(["select"], function (Fields\Field $field, NovaRequest $request, Fields\FormData $formData) {
                $field->applyToFields(function (Fields\Field $field, NovaRequest $request, Fields\FormData $formData) {
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

    public function fields(Request $request)
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

### Known Issues
- There was one but I can't remember what it was


## License

Nova Logo Url is open-sourced software licensed under the [MIT license](LICENSE.md).


