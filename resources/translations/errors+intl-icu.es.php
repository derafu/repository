<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    // Entities.
    'Attribute {attribute} does not exist in entity {entity}.' =>
        'El atributo {attribute} no existe en la entidad {entity}.',
    'Method {class}::{method}() does not exist.' =>
        'El método {class}::{method}() no existe.',
    'Could not create enum entity {entity}: {error}' =>
        'No se pudo crear la entidad enum {entity}: {error}',
    '{entity} must be a backed enum to be used as a repository entity.' =>
        '{entity} debe ser un enum con valores (backed enum) para usarse como entidad de un repositorio.',

    'Entity class {entity} does not exist.' =>
        'La clase de entidad {entity} no existe.',

    'The ID attribute "{attribute}" of enum entity {entity} must be an int or string; {type} given.' =>
        'El atributo ID "{attribute}" de la entidad enum {entity} debe ser int o string; se recibió {type}.',

    // Repositories.
    'Repository row {key} must be an array; {type} given.' =>
        'La fila {key} del repositorio debe ser un arreglo; se recibió {type}.',
    'In method {class}:find($id) an $id of type {type} was passed and only string and int are allowed.' =>
        'En el método {class}:find($id) se pasó un $id de tipo {type} y solo se permiten string e int.',
    'Offset cannot be negative.' =>
        'El offset no puede ser negativo.',
    'Limit cannot be negative.' =>
        'El límite no puede ser negativo.',
    'ArrayAccess must implement Traversable to be convertible.' =>
        'ArrayAccess debe implementar Traversable para ser convertible.',
    'Unknown field {field} in repository {entity}.' =>
        'Campo {field} desconocido en el repositorio {entity}.',
    'Invalid order direction {direction} for field {field}; only ASC and DESC are allowed.' =>
        'Dirección de orden {direction} inválida para el campo {field}; solo se permiten ASC y DESC.',
    'Unsupported criteria operator {operator}.' =>
        'Operador de criterio {operator} no soportado.',

    // Data sources.
    'Normalization setting {attribute} must be a string; {type} given.' =>
        'La opción de normalización {attribute} debe ser string; se recibió {type}.',
    'Could not read JSON file {file}.' =>
        'No se pudo leer el archivo JSON {file}.',
    'No data source configured for {source}.' =>
        'No hay una fuente de datos configurada para {source}.',
    'No data source resolver is registered for "{source}".' =>
        'No hay un resolvedor de fuentes de datos registrado para "{source}".',
    '{enum} must be a backed enum to be used as an enum data source.' =>
        '{enum} debe ser un enum con valores (backed enum) para usarse como fuente de datos.',
    'No file format reader is registered for the "{extension}" extension.' =>
        'No hay un lector de formato de archivo registrado para la extensión "{extension}".',
    'The JSON file {file} must decode to an array.' =>
        'El archivo JSON {file} debe decodificarse a un arreglo.',
    'The PHP file {file} must return an array.' =>
        'El archivo PHP {file} debe retornar un arreglo.',
    'The YAML file {file} must parse to an array.' =>
        'El archivo YAML {file} debe interpretarse como un arreglo.',

    'No repository factory configured for {repository}.' =>
        'No hay una fábrica de repositorios configurada para {repository}.',
    'Repository setting {setting} must be a string; {type} given.' =>
        'La opción de repositorio {setting} debe ser string; se recibió {type}.',
    'Repository class {class} must extend {base}. Register a factory for other repository implementations.' =>
        'La clase de repositorio {class} debe extender {base}. Registre una fábrica para otras implementaciones de repositorios.',
    'Repository class {class} must be instantiable.' =>
        'La clase de repositorio {class} debe poder instanciarse.',

    // Manager. The message of the error that happened is shown as is.
    '{message}' =>
        '{message}',
    'Entity class {class} does not exist. Could it be misspelled?' =>
        'La clase de entidad {class} no existe. ¿Estará mal escrita?',
    'Repository class {class} does not exist. Could it be misspelled?' =>
        'La clase de repositorio {class} no existe. ¿Estará mal escrita?',
];
