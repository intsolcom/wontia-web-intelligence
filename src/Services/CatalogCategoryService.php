<?php
namespace App\Services;

/**
 * CatalogCategoryService — compatibilidad con el importador del catálogo.
 *
 * La taxonomía y la asignación de productos viven en CatalogTaxonomyService.
 * Este servicio conserva los métodos que ya invoca el panel/admin y el
 * importador, pero delega en la fuente única de verdad.
 */
class CatalogCategoryService
{
    public function syncIfNeeded(): array
    {
        return (new CatalogTaxonomyService())->syncIfNeeded();
    }

    public function sync(): array
    {
        return (new CatalogTaxonomyService())->apply(true);
    }

    /** @deprecated Usar CatalogTaxonomyService::seedDatabase(). */
    public function assignProducts(array $ids = []): int
    {
        $map = CatalogTaxonomyService::buildMap(null, true);
        return CatalogTaxonomyService::assignProducts($map);
    }
}
