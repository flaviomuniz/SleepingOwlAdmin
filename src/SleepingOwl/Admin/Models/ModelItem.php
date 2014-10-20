<?php namespace SleepingOwl\Admin\Models;

use SleepingOwl\Admin\Admin;
use SleepingOwl\Admin\Columns\Column;
use SleepingOwl\Admin\Columns\Interfaces\ColumnInterface;
use SleepingOwl\Admin\Exceptions\MethodNotFoundException;
use SleepingOwl\Admin\Models\Filters\Filter;
use SleepingOwl\Admin\Models\Form\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SleepingOwl\Models\Interfaces\ModelWithOrderFieldInterface;

/**
 * Class ModelItem
 * @method $this as ()
 * @method $this denyCreating()
 * @method $this denyEditing(\Closure $closure)
 * @method $this denyDeleting(\Closure $closure)
 * @method $this denyEditingAndDeleting(\Closure $closure)
 */
class ModelItem
{
	/**
	 * @var ModelItem
	 */
	public static $current;

	/**
	 * @var string
	 */
	protected $modelClass;
	/**
	 * @var string
	 */
	protected $alias;
	/**
	 * @var Column[]
	 */
	public $columns;
	/**
	 * @var string[]
	 */
	protected $with = [];
	/**
	 * @var Filter[]
	 */
	protected $filters;
	/**
	 * @var bool
	 */
	protected $orderable;
	/**
	 * @var bool|\Closure
	 */
	protected $creatable = true;
	/**
	 * @var bool|\Closure
	 */
	protected $editable = true;
	/**
	 * @var bool|\Closure
	 */
	protected $deletable = true;
	/**
	 * @var string
	 */
	protected $title;
	/**
	 * @var Form
	 */
	protected $form;
	/**
	 * @var \SleepingOwl\Html\HtmlBuilder
	 */
	protected $htmlBuilder;

	/**
	 * @param $modelClass
	 */
	function __construct($modelClass)
	{
		$this->htmlBuilder = Admin::instance()->htmlBuilder;
		$this->modelClass = $modelClass;
		$this->alias = $this->getAliasFromClass($modelClass);
		$this->columns = [];
		$this->filters = [];
		$this->form = null;

		Admin::instance()->models->addItem($this);

		$this->form = new Form;

		$this->orderable = true;
		if (class_exists($class = $this->getModelClass()))
		{
			$instance = new $class;
			$this->orderable = ! ($instance instanceof ModelWithOrderFieldInterface);
		}
	}

	/**
	 * @return string
	 */
	public function renderTableAttributes()
	{
		$attributes = [];
		if ( ! $this->orderable)
		{
			$attributes['data-ordering'] = 'false';
		}
		return $this->htmlBuilder->attributes($attributes);
	}

	/**
	 * @return boolean
	 */
	public function isOrderable()
	{
		return $this->orderable;
	}

	/**
	 * @return bool
	 */
	public function isCreatable()
	{
		if (is_bool($this->creatable)) return $this->creatable;
		return ! call_user_func($this->creatable);
	}

	/**
	 * @param $instance
	 * @return bool
	 */
	public function isEditable($instance)
	{
		if (is_bool($this->editable)) return $this->editable;
		return ! call_user_func($this->editable, $instance);
	}

	/**
	 * @param $instance
	 * @return bool
	 */
	public function isDeletable($instance)
	{
		if (is_bool($this->deletable)) return $this->deletable;
		return ! call_user_func($this->deletable, $instance);
	}

	/**
	 * @return mixed
	 */
	public function getAlias()
	{
		return $this->alias;
	}

	/**
	 * @param mixed $alias
	 * @return $this
	 */
	public function setAlias($alias)
	{
		$this->alias = $alias;
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getWith()
	{
		return $this->with;
	}

	/**
	 * Set eager load fields
	 *
	 * @param $params
	 * @return ModelItem
	 */
	public function with($params = null)
	{
		if ( ! is_array($params))
		{
			$params = func_get_args();
		}
		$this->with = $params;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getModelClass()
	{
		return $this->modelClass;
	}

	public function getModelTable()
	{
		$instance = new $this->modelClass;
		return $instance->getTable();
	}

	/**
	 * @return array
	 */
	public function getColumns()
	{
		return $this->columns;
	}

	/**
	 * @param $callback
	 * @return $this
	 */
	public function columns($callback)
	{
		$old = static::$current;
		static::$current = $this;
		call_user_func($callback);
		Column::control();
		static::$current = $old;
		return $this;
	}

	/**
	 * @param ColumnInterface $column
	 */
	public function addColumn(ColumnInterface $column)
	{
		$this->columns[] = $column;
	}

	/**
	 * @param $title
	 * @return $this
	 */
	public function title($title)
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * @param $callback
	 * @return $this
	 */
	public function filters($callback)
	{
		$old = static::$current;
		static::$current = $this;
		call_user_func($callback);
		static::$current = $old;
		return $this;
	}

	/**
	 * @param $name
	 * @return Filter
	 */
	public static function filter($name)
	{
		return new Filter($name);
	}

	/**
	 * @param Filter $filter
	 */
	public function addFilter(Filter $filter)
	{
		$this->filters[] = $filter;
	}

	/**
	 * @param Builder $query
	 * @param $parameters
	 * @return array
	 */
	public function applyFilters(Builder $query, $parameters)
	{
		$titles = [];
		foreach ($this->filters as $filter)
		{
			$title = $filter->filter($query, $parameters);
			if ($title)
			{
				$titles[] = $title;
			}
		}
		return $titles;
	}

	/**
	 * @param $callback
	 * @return $this
	 */
	public function form($callback)
	{
		$old = static::$current;
		static::$current = $this;
		call_user_func($callback);
		static::$current = $old;
		return $this;
	}

	/**
	 * @return Form
	 */
	public function getForm()
	{
		return $this->form;
	}

	/**
	 * @param $modelClass
	 * @return string
	 */
	protected function getAliasFromClass($modelClass)
	{
		return Str::snake(Str::plural(class_basename($modelClass)));
	}

	/**
	 * @param $method
	 * @param $param
	 * @throws MethodNotFoundException
	 */
	public function __call($method, $param)
	{
		if ($method === 'as')
		{
			return call_user_func_array([
				$this,
				'setAlias'
			], $param);
		}
		if (preg_match('/^deny(?<types>.+)$/', $method, $attributes))
		{
			$closure = isset($param[0]) ? $param[0] : true;
			if (is_bool($closure)) $closure = ! $closure;

			$types = $attributes['types'];
			$types = strtolower(str_replace('ing', 'able', $types));
			$types = explode('and', $types);
			foreach ($types as $type)
			{
				$this->$type = $closure;
			}
			return $this;
		}
		throw new MethodNotFoundException(get_class($this), $method);
	}

}