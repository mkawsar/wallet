<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class AbstractRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    /**
     * Find a record by ID.
     */
    public function find(int $id, array $columns = ['*']): ?Model
    {
        return $this->model->find($id, $columns);
    }

    /**
     * Find a record by ID or fail.
     */
    public function findOrFail(int $id, array $columns = ['*']): Model
    {
        return $this->model->findOrFail($id, $columns);
    }

    /**
     * Build query from criteria.
     */
    protected function buildQueryFromCriteria(array $criteria)
    {
        $query = $this->model->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query;
    }

    /**
     * Find records by criteria.
     */
    public function findBy(array $criteria, array $columns = ['*']): Collection
    {
        return $this->buildQueryFromCriteria($criteria)->get($columns);
    }

    /**
     * Find a single record by criteria.
     */
    public function findOneBy(array $criteria, array $columns = ['*']): ?Model
    {
        return $this->buildQueryFromCriteria($criteria)->first($columns);
    }

    /**
     * Create a new record.
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update a record.
     */
    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    /**
     * Delete a record.
     */
    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    /**
     * Paginate results.
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return $this->model->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Get a new query builder instance.
     */
    public function query()
    {
        return $this->model->newQuery();
    }

    /**
     * Lock a row for update (for preventing race conditions).
     */
    public function lockForUpdate(int $id): ?Model
    {
        return $this->model->lockForUpdate()->find($id);
    }
}
