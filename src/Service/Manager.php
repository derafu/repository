<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Worker;

use Derafu\Config\Trait\ConfigurableTrait;
use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Contract\ManagerInterface;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\Repository\Entity;
use Derafu\Repository\Exception\ManagerException;
use Derafu\Repository\Mapping as DEM;
use Derafu\Repository\Repository;
use Exception;
use ReflectionClass;

/**
 * Servicio de administración de repositorios.
 */
class ManagerWorker implements ManagerInterface
{
    use ConfigurableTrait;

    /**
     * Sufijo de la interfaz.
     *
     * @var string
     */
    private const ENTITY_INTERFACE_SUFFIX = 'Interface';

    /**
     * Namespace de la interfaz.
     *
     * Importante: solo el nivel inmediatamente superior.
     *
     * @var string
     */
    private const ENTITY_INTERFACE_NAMESPACE = 'Contract';

    /**
     * Sufijo de la clase de entidad.
     *
     * Importante: no se utilizan sufijos en entidades, pero se deja
     * estandrizado acá en la constante.
     *
     * @var string
     */
    private const ENTITY_CLASS_SUFFIX = ''; // En blanco a propósito.

    /**
     * Namespace de la entidad.
     *
     * Importante: solo el nivel inmediatamente superior.
     *
     * @var string
     */
    private const ENTITY_CLASS_NAMESPACE = 'Entity';

    /**
     * Esquema de configuración del worker.
     *
     * @var array
     */
    protected array $configurationSchema = [
        'entityClass' => [
            'types' => 'string',
            'default' => Entity::class,
        ],
        'repositoryClass' => [
            'types' => 'string',
            'default' => Repository::class,
        ],
    ];

    /**
     * Listado de repositorios que ya han sido cargados desde sus orígenes de
     * datos.
     *
     * @var array<string,RepositoryInterface>
     */
    private array $loaded = [];

    /**
     * Constructor del worker.
     *
     * @param DataProviderInterface $dataProvider
     */
    public function __construct(
        private DataProviderInterface $dataProvider
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getRepository(string $repository): RepositoryInterface
    {
        // Si el repositorio no está cargado se carga.
        if (!isset($this->loaded[$repository])) {
            try {
                $this->loaded[$repository] = $this->loadRepository($repository);
            } catch (Exception $e) {
                throw new ManagerException($e->getMessage());
            }
        }

        // Retornar el repositorio solicitado.
        return $this->loaded[$repository];
    }

    /**
     * Carga un repositorio con los datos desde un origen de datos.
     *
     * @param string $repository
     * @return RepositoryInterface
     */
    private function loadRepository(string $repository): RepositoryInterface
    {
        // Resolver el repositorio que se debe crear.
        $entityClass = $this->resolveEntityClass($repository);
        $repositoryClass = $this->resolveRepositoryClass($entityClass);

        // Si el repositorio implementa RepositoryInterface es un repositorio
        // con datos que se obtienen desde DatasourceProvider y se deben cargar
        // al repositorio (en memoria).
        if (in_array(RepositoryInterface::class, class_implements($repositoryClass))) {
            $data = $this->dataProvider->fetch($repository);
            $instance = new $repositoryClass($data, $entityClass);
        }

        // Si el repositorio es otro tipo de clase se instancia sin datos y se
        // espera que el repositorio resuelva su carga (ej: desde una base de
        // datos).
        else {
            // TODO: Mejorar retorno de RepositoryInterface con este caso.
            $instance = new $repositoryClass();
        }

        // Asignar la instnacia del repositorio como cargada y retornar.
        $this->loaded[$repository] =  $instance;
        return $this->loaded[$repository];
    }

    /**
     * Determina la clase de la entidad que se debe utilizar con el repositorio.
     *
     * El identificador del repositorio puede ser el FQCN de la clase de la
     * entidad (lo ideal) o un identificador genérico que se resolverá a la
     * clase de entidad configurada en el worker.
     *
     * Si el identificador del repositorio es una interfaz se espera:
     *
     *   - La interfaz esté dentro de un namespace "Contract".
     *   - La interfaz tenga como sufijo "Interface".
     *   - La entidad esté dentro del namespace "Entity" sin sufijo.
     *
     * @param string $repository Identificador del repositorio.
     * @return string Clase de la entidad para el repositorio.
     */
    private function resolveEntityClass(string $repository): string
    {
        // Si el identificador del repositorio "parece" clase se asume que es la
        // clase de la entidad o una interfaz de la entidad en el mismo
        // namespace.
        if (str_contains($repository, '\\')) {
            $entityClass = $this->guessEntityClass($repository);
        }

        // Se entrega la clase de la entidad por defecto cuando el identificador
        // no es una clase. Si no tiene "\" entonces no tiene namespace, en este
        // caso se asume no es una clase.
        else {
            $entityClass = $this->getConfiguration()->get('entityClass');
        }

        // Lanzar error si la clase no existe pues podría haber sido mal
        // escrita por el programador.
        if (!class_exists($entityClass)) {
            throw new ManagerException(sprintf(
                'La clase de entidad %s no existe. ¿Estará mal escrita?',
                $entityClass
            ));
        }

        // Entregar la clase de la entidad.
        return $entityClass;
    }

    /**
     * Adivina la clase de entidad en caso que la clase de entrada sea una
     * interfaz.
     *
     * @param string $class
     * @return string
     */
    private function guessEntityClass(string $class): string
    {
        // Si la clase es una interfaz se asume una clase de entidad en el mismo
        // namespace. Esto es rígido y requiere un formato para el FQCN de la
        // clase y la interfaz. Por ahora es suficiente.
        if (str_ends_with($class, self::ENTITY_INTERFACE_SUFFIX)) {
            $length = strlen($class) - strlen(self::ENTITY_INTERFACE_SUFFIX);
            return str_replace(
                '\\' . self::ENTITY_INTERFACE_NAMESPACE .  '\\',
                '\\' . self::ENTITY_CLASS_NAMESPACE  . '\\',
                substr($class, 0, $length)
            ) . self::ENTITY_CLASS_SUFFIX;
        }

        // Se entrega la misma clase, pues no tiene el formato esperado para
        // adiviar la clase de entidad.
        return $class;
    }

    /**
     * Determina la clase del repositorio que se debe utilizar para una entidad.
     *
     * Si la clase de la entidad no provee la información de su repositorio
     * asociado se entregará la clase de repositorio configurada en el worker.
     *
     * @param string $entityClass Clase de la entidad.
     * @return string Clase del repositorio.
     */
    private function resolveRepositoryClass(string $entityClass): string
    {
        // Se trata de obtener la clase del repositorio desde el método estático
        // de la entidad getRepositoryClass().
        if (method_exists($entityClass, 'getRepositoryClass')) {
            $repositoryClass = call_user_func([$entityClass, 'getRepositoryClass']);
            if ($repositoryClass) {
                return $repositoryClass;
            }
        }

        // Se trata de obtener la clase del repositorio desde un atributo PHP de
        // la clase de la entidad.
        $reflectionClass = new ReflectionClass($entityClass);
        $attributes = $reflectionClass->getAttributes(DEM\Entity::class);
        if (!empty($attributes)) {
            $repositoryClass = $attributes[0]->newInstance()->repositoryClass;
            if ($repositoryClass) {
                return $repositoryClass;
            }
        }

        // Se entrega la clase del repositorio por defecto configurada.
        $repositoryClass = $this->getConfiguration()->get('repositoryClass');

        // Lanzar error si la clase no existe pues podría haber sido mal
        // escrita por el programador.
        if (!class_exists($repositoryClass)) {
            throw new ManagerException(sprintf(
                'La clase de repositorio %s no existe. ¿Estará mal escrita?',
                $repositoryClass
            ));
        }

        // Entregar clase del repositorio.
        return $repositoryClass;
    }
}
