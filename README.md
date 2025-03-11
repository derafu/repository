# Derafu: Repository - Lightweight File Data Source Management for PHP

![GitHub last commit](https://img.shields.io/github/last-commit/derafu/repository/main)
![CI Workflow](https://github.com/derafu/repository/actions/workflows/ci.yml/badge.svg?branch=main&event=push)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/derafu/repository)
![GitHub Issues](https://img.shields.io/github/issues-raw/derafu/repository)
![Total Downloads](https://poser.pugx.org/derafu/repository/downloads)
![Monthly Downloads](https://poser.pugx.org/derafu/repository/d/monthly)

A lightweight, flexible PHP library for managing data repositories with multiple data sources and seamless integration with PHP frameworks.

## Why Derafu\Repository?

### 🚀 **Simplified Data Management**

Traditional PHP data repositories often:

- Are tightly coupled to specific storage mechanisms.
- Require complex configuration.
- Lack flexibility across different data sources.

### 🔥 **What Makes Derafu\Repository Unique?**

| Feature                     | Derafu\Repository | Traditional Repositories |
|-----------------------------|-------------------|--------------------------|
| **File-Based Data Sources** | ✅ Yes            | ❌ No                    |
| **Lightweight Design**      | ✅ Yes            | ❌ No                    |
| **Simple Configuration**    | ✅ Yes            | ❌ No                    |
| **Framework Agnostic**      | ✅ Yes            | ⚠️ Varies                 |

---

## Features

- ✅ **Multiple Data Source Support** – Load data from PHP arrays, JSON, YAML files.
- ✅ **Generic Entity Handling** – Work with any type of data structure.
- ✅ **Flexible Querying** – Find, filter, and order data with ease.
- ✅ **Doctrine Collections Compatible** – Use Doctrine Criteria for advanced filtering.
- ✅ **Zero Heavy Dependencies** – Lightweight and performance-focused.
- ✅ **PHP 8+ Ready** – Leverages modern PHP features.

---

## Installation

Install via Composer:

```bash
composer require derafu/repository
```

## Basic Usage

```php
use Derafu\Repository\Repository;

// Load data from a PHP file or array.
$data = [
    'products' => [
        'prod-001' => [
            'name' => 'Laptop XPS',
            'category' => 'computers',
            'price' => 1299.99,
        ],
        // More products...
    ],
];

// Create a repository.
$repository = new Repository($data, idAttribute: 'id');

// Find all products.
$allProducts = $repository->findAll();

// Find products by criteria.
$computerProducts = $repository->findBy([
    'category' => 'computers'
]);

// Find a single product.
$laptop = $repository->findOneBy([
    'name' => 'Laptop XPS',
]);
```

## Advanced Usage with Doctrine Criteria

```php
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;

// Advanced filtering with Doctrine Criteria.
$expensiveProducts = $repository->findByCriteria(
    Criteria::create()
        ->where(Criteria::expr()->gt('price', 1000))
        ->orderBy(['price' => Order::Descending])
);
```

## Supported Data Sources

- PHP Arrays.
- PHP Files returning arrays.
- JSON Files.
- YAML Files.

## Performance Considerations

- Optimized for small to medium-sized datasets.
- In-memory data management.
- Recommended for configuration, lookup tables, and static data.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
