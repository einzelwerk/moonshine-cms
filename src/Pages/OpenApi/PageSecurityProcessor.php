<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\OpenApi;

use Illuminate\Container\Container;
use LogicException;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;

final class PageSecurityProcessor
{
    public const string CONFIG = 'cms-pages.openapi.security';

    /** @var list<string> */
    public const array OPERATIONS = ['CmsResolveV1', 'CmsShowBlockV1'];

    public function __invoke(Analysis $analysis): void
    {
        $container = Container::getInstance();

        if (! $analysis->openapi instanceof OA\OpenApi || ! $container->bound('config')) {
            return;
        }

        $security = self::security(
            $container->make('config')->get(self::CONFIG, []),
            self::declaredSchemes($analysis->openapi),
        );

        foreach ($analysis->getAnnotationsOfType(OA\Operation::class) as $operation) {
            if (\in_array($operation->operationId, self::OPERATIONS, true)) {
                $operation->security = $security;
            }
        }
    }

    /**
     * @param  list<string>  $declared
     * @return list<array<string, list<string>>>
     */
    private static function security(mixed $configured, array $declared): array
    {
        if (! \is_array($configured) || ! array_is_list($configured)) {
            throw new LogicException('['.self::CONFIG.'] must be a list of security requirements.');
        }

        $security = [];

        foreach ($configured as $requirement) {
            if (! \is_array($requirement)) {
                throw new LogicException('['.self::CONFIG.'] lists a security requirement that is not an array.');
            }

            $checked = [];

            foreach ($requirement as $scheme => $scopes) {
                if (! \in_array($scheme, $declared, true)) {
                    throw new LogicException('['.self::CONFIG.'] names the security scheme ['.$scheme.'] the document does not declare.');
                }

                if (! \is_array($scopes) || array_filter($scopes, is_string(...)) !== $scopes || ! array_is_list($scopes)) {
                    throw new LogicException('['.self::CONFIG.'] gives the scheme ['.$scheme.'] anything but a list of scopes.');
                }

                $checked[$scheme] = $scopes;
            }

            $security[] = $checked;
        }

        return $security;
    }

    /**
     * @return list<string>
     */
    private static function declaredSchemes(OA\OpenApi $openapi): array
    {
        if (Undefined::isDefault($openapi->components) || Undefined::isDefault($openapi->components->securitySchemes)) {
            return [];
        }

        return array_map(
            static fn (OA\SecurityScheme $scheme): string => $scheme->securityScheme,
            $openapi->components->securitySchemes,
        );
    }
}
