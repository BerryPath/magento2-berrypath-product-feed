# Product Feed for Magento 2

Magento 2 module for generating product feeds per store view.

The feed is intended for external product-data consumers such as guided-selling
platforms, product recommendation tools, comparison engines and Google Shopping
feed pipelines. It exposes Magento product data in a stable XML format and can
be extended with extra product attributes from the admin configuration.

## Installation

```bash
composer require berrypath/magento2-berrypath-product-feed
bin/magento module:enable BerryPath_ProductFeed
bin/magento setup:upgrade
bin/magento cache:flush
```

For local `app/code` development, place it at:

```text
app/code/BerryPath/ProductFeed
```

## Configuration

```text
Stores > Configuration > BerryPath > Product Feed
```

Feed endpoint:

```text
/berrypath/feed/id/{store_id}
```

Optional parameters:

- `pid`: fetch one product by the configured Product ID source.

The preview URL in the admin is limited to the first 25 products. The XML feed endpoint exports all products.

## Feed Types

The default feed type is Generic XML.

Google Shopping RSS 2.0 can be enabled from the admin. That output uses the
Google `g:` namespace and emits Google product attributes such as `g:id`,
`g:title`, `g:price`, `g:availability` and optional `g:shipping`.

## Current Feed Fields

The feed includes core product data such as ID, SKU, type, name, URL, image,
price, final price, currency, salability, visibility, tax class, categories and
review summary data. Configurable, grouped and bundle product prices use the
Magento price index so parent products do not export `0.00` prices when indexed
prices are available.

## Possible Channel Usage

The Google Shopping feed mode covers the core XML/RSS format and namespace.
Some merchants may still need channel-specific enrichment such as Google product
category, GTIN/MPN/brand mapping, promotion feeds or custom title and description
optimization.

## BerryPath

BerryPath helps ecommerce teams build guided selling flows, product finders and
guided product advice experiences. Learn more at [berrypath.eu](https://www.berrypath.eu).

For embedding BerryPath advice flows in Magento category pages, product pages
and CMS/widget placements, use the companion module:

- Package: [`berrypath/magento2-berrypath-flow`](https://github.com/BerryPath/magento2-berrypath-flow)
