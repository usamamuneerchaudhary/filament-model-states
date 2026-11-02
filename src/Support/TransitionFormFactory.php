<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Usamamuneerchaudhary\FilamentModelStates\Exceptions\UnresolvableTransitionParameter;

class TransitionFormFactory
{
    /**
     * @param  class-string  $transitionClass
     * @param  array<string, mixed>  $arguments
     */
    public function plan(string $transitionClass, Model $record, array $arguments): TransitionFormPlan
    {
        $parameters = $this->userParameters($transitionClass, $record);
        $schema = $this->schemaFor($transitionClass, $record, $parameters);
        $needsInput = $this->needsInput($transitionClass, $parameters, $arguments, $schema);

        if ($needsInput) {
            return new TransitionFormPlan(needsInput: true, schema: $schema);
        }

        return new TransitionFormPlan(
            needsInput: false,
            schema: $schema,
            positionalArguments: $this->positionalArguments($parameters, $arguments),
        );
    }

    /**
     * @param  class-string  $transitionClass
     * @return list<ReflectionParameter>
     */
    protected function userParameters(string $transitionClass, Model $record): array
    {
        try {
            $constructor = (new ReflectionClass($transitionClass))->getConstructor();
        } catch (ReflectionException) {
            return [];
        }

        if ($constructor === null) {
            return [];
        }

        $defaultTransition = $this->isDefaultTransition($transitionClass);
        $parameters = [];
        $skippedModel = false;

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                continue;
            }

            if (! $skippedModel && $this->isModelParameter($parameter, $record)) {
                $skippedModel = true;

                continue;
            }

            if ($defaultTransition && $this->isDefaultTransitionInfrastructure($parameter)) {
                continue;
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * @param  class-string  $transitionClass
     * @param  list<ReflectionParameter>  $parameters
     * @return list<Component>
     */
    protected function schemaFor(string $transitionClass, Model $record, array $parameters): array
    {
        if ($this->hasTransitionForm($transitionClass)) {
            return $this->transitionForm($transitionClass, $record);
        }

        $schema = [];

        foreach ($parameters as $parameter) {
            $component = $this->componentFor($parameter);

            if ($component === null) {
                if (! $parameter->isOptional()) {
                    throw UnresolvableTransitionParameter::forParameter($transitionClass, $parameter->getName());
                }

                continue;
            }

            $schema[] = $component;
        }

        return $schema;
    }

    /**
     * @param  class-string  $transitionClass
     * @param  list<ReflectionParameter>  $parameters
     * @param  array<string, mixed>  $arguments
     * @param  list<Component>  $schema
     */
    protected function needsInput(string $transitionClass, array $parameters, array $arguments, array $schema): bool
    {
        foreach ($parameters as $parameter) {
            if (! $this->parameterIsSatisfied($parameter, $arguments)) {
                return true;
            }
        }

        return $arguments === [] && $schema !== [] && $this->hasTransitionForm($transitionClass);
    }

    /**
     * @param  list<ReflectionParameter>  $parameters
     * @param  array<string, mixed>  $arguments
     * @return list<mixed>
     */
    protected function positionalArguments(array $parameters, array $arguments): array
    {
        $positional = [];

        foreach ($parameters as $parameter) {
            if (! array_key_exists($parameter->getName(), $arguments)) {
                break;
            }

            $positional[] = $this->castArgument($parameter, $arguments[$parameter->getName()]);
        }

        return $positional;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function parameterIsSatisfied(ReflectionParameter $parameter, array $arguments): bool
    {
        if (array_key_exists($parameter->getName(), $arguments)) {
            return true;
        }

        return $parameter->isOptional();
    }

    protected function componentFor(ReflectionParameter $parameter): ?Component
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return null;
        }

        $name = $parameter->getName();
        $required = ! $parameter->isOptional();
        $typeName = $type->getName();

        if ($typeName === 'string') {
            return TextInput::make($name)->label(Str::headline($name))->required($required);
        }

        if ($typeName === 'int' || $typeName === 'float') {
            return TextInput::make($name)->label(Str::headline($name))->numeric()->required($required);
        }

        if ($typeName === 'bool') {
            return Toggle::make($name)->label(Str::headline($name))->required($required);
        }

        if (enum_exists($typeName) && is_subclass_of($typeName, BackedEnum::class)) {
            $options = [];

            foreach ($typeName::cases() as $case) {
                $label = $case->name;

                if (method_exists($case, 'label')) {
                    $label = $case->label();
                }

                $options[$case->value] = $label;
            }

            return Select::make($name)->label(Str::headline($name))->options($options)->required($required);
        }

        return null;
    }

    /**
     * @param  class-string  $transitionClass
     */
    protected function hasTransitionForm(string $transitionClass): bool
    {
        return method_exists($transitionClass, 'transitionForm');
    }

    /**
     * @param  class-string  $transitionClass
     * @return list<Component>
     */
    protected function transitionForm(string $transitionClass, Model $record): array
    {
        $method = new ReflectionMethod($transitionClass, 'transitionForm');

        if ($method->isStatic()) {
            $schema = $transitionClass::transitionForm();
        } else {
            $schema = $this->instantiateForForm($transitionClass, $record)->transitionForm();
        }

        if (! is_array($schema)) {
            return [];
        }

        $components = [];

        foreach ($schema as $component) {
            if ($component instanceof Component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * @param  class-string  $transitionClass
     */
    protected function instantiateForForm(string $transitionClass, Model $record): object
    {
        $constructor = (new ReflectionClass($transitionClass))->getConstructor();

        if ($constructor === null) {
            return new $transitionClass;
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            if ($this->isModelParameter($parameter, $record)) {
                $arguments[] = $record;

                continue;
            }

            if ($parameter->isOptional()) {
                break;
            }

            throw UnresolvableTransitionParameter::forParameter($transitionClass, 'transitionForm');
        }

        return new $transitionClass(...$arguments);
    }

    protected function isModelParameter(ReflectionParameter $parameter, Model $record): bool
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $typeName = $type->getName();

        return $typeName === Model::class || is_a($record, $typeName) || is_a($typeName, Model::class, true);
    }

    /**
     * @param  class-string  $transitionClass
     */
    protected function isDefaultTransition(string $transitionClass): bool
    {
        $defaultTransition = 'Spatie\\ModelStates\\DefaultTransition';

        return class_exists($defaultTransition)
            && ($transitionClass === $defaultTransition || is_subclass_of($transitionClass, $defaultTransition));
    }

    protected function isDefaultTransitionInfrastructure(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        if ($parameter->getName() === 'field' && $type->getName() === 'string') {
            return true;
        }

        $stateClass = 'Spatie\\ModelStates\\State';

        return class_exists($stateClass) && is_a($type->getName(), $stateClass, true);
    }

    protected function castArgument(ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return $value;
        }

        if ($value === null && $type->allowsNull()) {
            return null;
        }

        $typeName = $type->getName();

        if (! $type->isBuiltin() && enum_exists($typeName) && is_subclass_of($typeName, BackedEnum::class)) {
            if ($value instanceof BackedEnum) {
                return $value;
            }

            return $typeName::from(is_scalar($value) ? (string) $value : '');
        }

        return match ($typeName) {
            'string' => is_scalar($value) ? (string) $value : '',
            'int' => is_numeric($value) ? (int) $value : 0,
            'float' => is_numeric($value) ? (float) $value : 0.0,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            default => $value,
        };
    }
}
