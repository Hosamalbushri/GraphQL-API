<?php

namespace Webkul\GraphQLAPI\Queries\Admin\Configuration;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Webkul\AdminTheme\Helpers\AdminHelper;

class AdminHelperQuery
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected AdminHelper $adminHelper
    ) {}

    /**
     * Check if postal code is required for addresses.
     */
    public function isPostCodeRequired(mixed $rootValue, array $args, GraphQLContext $context): bool
    {
        return $this->adminHelper->isPostCodeRequired();
    }

    /**
     * Check if postal code should be shown in address forms.
     */
    public function showPostalCode(mixed $rootValue, array $args, GraphQLContext $context): bool
    {
        return $this->adminHelper->show_postal_code();
    }

    /**
     * Check if company name should be shown in address forms.
     */
    public function showCompanyName(mixed $rootValue, array $args, GraphQLContext $context): bool
    {
        return $this->adminHelper->show_company_name();
    }

    /**
     * Check if tax number should be shown in address forms.
     */
    public function showTaxNumber(mixed $rootValue, array $args, GraphQLContext $context): bool
    {
        return $this->adminHelper->show_tax_number();
    }

    /**
     * Get the default country code.
     */
    public function getDefaultCountry(mixed $rootValue, array $args, GraphQLContext $context): ?string
    {
        return $this->adminHelper->get_default_country();
    }

    /**
     * Get all address configuration settings.
     */
    public function getAddressConfiguration(mixed $rootValue, array $args, GraphQLContext $context): array
    {
        return [
            'isPostCodeRequired' => $this->adminHelper->isPostCodeRequired(),
            'showPostalCode'     => $this->adminHelper->show_postal_code(),
            'showCompanyName'    => $this->adminHelper->show_company_name(),
            'showTaxNumber'      => $this->adminHelper->show_tax_number(),
            'defaultCountry'     => $this->adminHelper->get_default_country(),
        ];
    }
}
