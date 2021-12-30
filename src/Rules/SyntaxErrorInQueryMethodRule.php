<?php

declare(strict_types=1);

namespace staabm\PHPStanDba\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use staabm\PHPStanDba\QueryReflection\QueryReflection;

/**
 * @implements Rule<MethodCall>
 */
final class SyntaxErrorInQueryMethodRule implements Rule
{
    /**
     * @var list<string>
     */
    private $classMethods;

    /**
     * @param list<string> $classMethods
     */
    public function __construct(array $classMethods)
    {
        $this->classMethods = $classMethods;
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methodReflection = $scope->getMethodReflection($scope->getType($node->var), $node->name->toString());
        if (null === $methodReflection) {
            return [];
        }

        $unsupportedMethod = true;
        foreach ($this->classMethods as $classMethod) {
            list($className, $methodName) = explode('::', $classMethod);

            if ($methodName === $methodReflection->getName() && $className === $methodReflection->getDeclaringClass()->getName()) {
                $unsupportedMethod = false;
                break;
            }
        }

        if ($unsupportedMethod) {
            return [];
        }

        $args = $node->getArgs();
        $errors = [];

        $queryReflection = new QueryReflection();
        if ($queryReflection->containsSyntaxError($args[0]->value, $scope)) {
            $errors[] = RuleErrorBuilder::message('Query contains a syntax error.')->line($node->getLine())->build();
        }

        return $errors;
    }
}
