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

    // Repositories.
    'In method {class}:find($id) an $id of type {type} was passed and only string and int are allowed.' =>
        'En el método {class}:find($id) se pasó un $id de tipo {type} y solo se permiten string e int.',
    'Offset cannot be negative.' =>
        'El offset no puede ser negativo.',
    'Limit cannot be negative.' =>
        'El límite no puede ser negativo.',

    // Data sources.
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

    // Manager. The message of the error that happened is shown as is.
    '{message}' =>
        '{message}',
    'Entity class {class} does not exist. Could it be misspelled?' =>
        'La clase de entidad {class} no existe. ¿Estará mal escrita?',
    'Repository class {class} does not exist. Could it be misspelled?' =>
        'La clase de repositorio {class} no existe. ¿Estará mal escrita?',
];
