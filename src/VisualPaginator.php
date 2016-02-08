<?php

/**
 * This file is part of the AlesWita\Components\VisualPaginator
 * Copyright (c) 2016 Ales Wita (aleswita+github@gmail.com)
 */

namespace AlesWita\Components;

use Nette;
use Nette\Application;
use Nette\Http;
use Nette\Utils;


/**
 * @author Ales Wita
 * @author David Grudl
 * @license MIT
 */
class VisualPaginator extends Application\UI\Control
{
	/** default session section constant */
	const SESSION_SECTION = "Visual-Paginator";

	/** arrays with predefined templates */
	const TEMPLATE_NORMAL = [
		"main" => __DIR__ . "/templates/normal/main.latte",
		"paginator" => __DIR__ . "/templates/normal/paginator.latte",
		"itemsPerPage" => __DIR__ . "/templates/normal/items-per-page.latte"
	];
	const TEMPLATE_BOOTSTRAP_V3 = [
		"main" => __DIR__ . "/templates/bootstrap-v3/main.latte",
		"paginator" => __DIR__ . "/templates/bootstrap-v3/paginator.latte",
		"itemsPerPage" => __DIR__ . "/templates/bootstrap-v3/items-per-page.latte"
	];

	/** ******************** */

	/** @persistent */
	public $page;

	/** @persistent */
	public $itemsPerPage;

	/** @var callable[] */
	public $onPaginate;

	/** ******************** */

	/** @var Nette\Utils\Paginator */
	private $paginator;

	/** @var Nette\Http\Session */
	private $session;

	/** @var Nette\Localization\ITranslator */
	private $translator;

	/** @var Nette\Http\SessionSection */
	private $sessionSection;

	/** @var string */
	private $itemsPerPageReposity;

	/** @var bool */
	private $canSetItemsPerPage = FALSE;

	/** @var array */
	private $itemsPerPageList = [10 => "10", 20 => "20", 30 => "30", 40 => "40", 50 => "50", 100 => "100"];

	/** @var array */
	private $paginatorTemplate = self::TEMPLATE_NORMAL;

	/** @var array */
	private $texts = [
		"send" => "Send",
		"itemsPerPage" => "Items per page"
	];

	/** @var bool */
	private $ajax = FALSE;

	/** ********** getters - start ********** */

	/**
	 * @return Nette\Utils\Paginator
	 */
	public function getPaginator(): Nette\Utils\Paginator {
		if ($this->paginator === NULL) {
			$this->paginator = new Utils\Paginator;
		}
		$this->paginator->page = ($this->page === NULL ? 1 : $this->page);
		$this->paginator->itemsPerPage = ($this->itemsPerPage === NULL ? 10 : $this->itemsPerPage);
		return $this->paginator;
	}

	/**
	 * @return int
	 */
	public function getOffset(): int {
		return $this->getPaginator()->offset;
	}

	/**
	 * @return int
	 */
	public function getItemsPerPage(): int {
		return $this->getPaginator()->itemsPerPage;
	}

	/**
	 * @return string
	 */
	private function getSessionReposity(): string {
		if ($this->itemsPerPageReposity !== NULL) {
			return $this->itemsPerPageReposity;
		} else {
			$name = $this->presenter->getRequest()->getPresenterName();
			$params = $this->presenter->getRequest()->getParameters();
			$match = Utils\Strings::match($name, "~^(([a-zA-Z0-9_]+):([a-zA-Z0-9_]+))|([a-zA-Z0-9_]+)$~");

			if (isset($match[2]) && isset($match[3]) && $match[2] !== "" && $match[3] !== "") {// module:presenter
				return Utils\Strings::lower("{$match[2]}-{$match[3]}-{$params["action"]}");
			} elseif (isset($match[4]) && $match[4] !== "") {// presenter
				return Utils\Strings::lower("{$match[4]}-{$params["action"]}");
			} else {
				return "default";
			}
		}
	}

	/** ********** getters - end ********** */

	/** ********** setters - start ********** */

	/**
	 * @param int
	 * @return self
	 * @throw Nette\InvalidArgumentException
	 */
	public function setItemsPerPage(int $num): self {
		if ($this->canSetItemsPerPage && !in_array($num, array_keys($this->itemsPerPageList), TRUE)) {
			throw new Nette\InvalidArgumentException("\$itemsPerPageList array haven't value '{$num}'.");
		}
		if ($this->session !== NULL) {
			$this->sessionSection->{$this->getSessionReposity()} = $num;
		}
		$this->itemsPerPage = $num;
		return $this;
	}

	/**
	 * @param Nette\Http\Session
	 * @param string
	 * @param string
	 * @return self
	 */
	public function setSession(Http\Session $session, string $itemsPerPageReposity = NULL, string $section = self::SESSION_SECTION): self {
		$this->session = $session;
		$this->sessionSection = $session->getSection($section);
		if ($itemsPerPageReposity !== NULL) {
			$this->itemsPerPageReposity = Utils\Strings::lower($itemsPerPageReposity);
		}
		return $this;
	}

	/**
	 * @param Nette\Localization\ITranslator
	 * @return self
	 */
	public function setTranslator(Nette\Localization\ITranslator $translator): self {
		$this->translator = $translator;
		return $this;
	}

	/**
	 * @param bool
	 * @return self
	 */
	public function setCanSetItemsPerPage(bool $bool): self {
		$this->canSetItemsPerPage = $bool;
		return $this;
	}

	/**
	 * @param array
	 * @return self
	 * @throw Nette\InvalidArgumentException
	 */
	public function setItemsPerPageList(array $arr): self {
		if ($arr !== array_filter($arr, function ($s){return is_numeric($s);})) {
			throw new Nette\InvalidArgumentException("Keys in \$itemsPerPageList array must be numeric.");
		}
		$this->itemsPerPageList = $arr;
		return $this;
	}

	/**
	 * @param array
	 * @return self
	 * @throw Nette\InvalidArgumentException
	 */
	public function setPaginatorTemplate(array $template): self {
		if (array_keys($template) !== array_keys(self::TEMPLATE_NORMAL)) {
			throw new Nette\InvalidArgumentException("Template array must have these keys: main, paginator and itemsPerPage.");
		}
		$this->paginatorTemplate = $template;
		return $this;
	}

	/**
	 * @param string
	 * @return self
	 */
	public function setText(string ... $s): self {
		$this->texts[$s[0]] = $s[1];
		return $this;
	}

	/**
	 * @param bool
	 * @return self
	 */
	public function setAjax(bool $bool): self {
		$this->ajax = $bool;
		return $this;
	}

	/**
	 * @param int
	 * @param self
	 */
	public function setItemCount(int $count): self {
		$this->getPaginator()->itemCount = $count;
		return $this;
	}

	/** ********** setters - end ********** */

	/**
	 * @param array
	 */
	public function loadState(array $params)
	{
		parent::loadState($params);

		// get items per page from session
		if ($this->session !== NULL && $this->canSetItemsPerPage) {
			if (isset($this->sessionSection->{$this->getSessionReposity()}) && in_array($this->sessionSection->{$this->getSessionReposity()}, array_keys($this->itemsPerPageList), TRUE)) {
				$this->setItemsPerPage($this->sessionSection->{$this->getSessionReposity()});
			} else {
				unset($this->sessionSection->{$this->getSessionReposity()});
			}
		}


		$this->getPaginator()->page = $this->page;
		$this->getPaginator()->itemsPerPage = $this->itemsPerPage;
		$this->page = $this->getPaginator()->page;
        $this->itemsPerPage = $this->getPaginator()->itemsPerPage;


		$this["itemsPerPage"]->setDefaults([
			"itemsPerPage" => $this->itemsPerPage
		]);


		if ($this->onPaginate !== NULL) {
			if (is_array($this->onPaginate)) {
				foreach ($this->onPaginate as $handler) {
					Utils\Callback::invoke($handler);
				}
			}
		}
	}

	public function render()
	{
		$paginator = $this->getPaginator();

		if ($paginator->pageCount < 2) {
			$foo = [$paginator->page];
		} else {
			$foo = range(max($paginator->firstPage, $paginator->page - 3), min($paginator->lastPage, $paginator->page + 3));
			$count = 4;
			$quotient = ($paginator->pageCount - 1) / $count;

			for ($i = 0; $i <= $count; $i++) {
				$foo[] = round($quotient * $i) + $paginator->firstPage;
			}
			sort($foo);
		}

		$this->template->steps = array_values(array_unique($foo));
		$this->template->itemsPerPage = $this->canSetItemsPerPage;
		$this->template->paginator = $paginator;
		$this->template->ajax = $this->ajax;

		$this->template->setFile($this->paginatorTemplate["main"]);
		$this->template->render();
	}

	public function renderPaginator()
	{
		$paginator = $this->getPaginator();

		if ($paginator->pageCount < 2) {
			$foo = [$paginator->page];
		} else {
			$foo = range(max($paginator->firstPage, $paginator->page - 3), min($paginator->lastPage, $paginator->page + 3));
			$count = 4;
			$quotient = ($paginator->pageCount - 1) / $count;

			for ($i = 0; $i <= $count; $i++) {
				$foo[] = round($quotient * $i) + $paginator->firstPage;
			}
			sort($foo);
		}

		$this->template->steps = array_values(array_unique($foo));
		$this->template->paginator = $paginator;
		$this->template->ajax = $this->ajax;

		$this->template->setFile($this->paginatorTemplate["paginator"]);
		$this->template->render();
	}

	public function renderItemsPerPage()
	{
		$this->template->itemsPerPage = $this->canSetItemsPerPage;
		$this->template->ajax = $this->ajax;

		$this->template->setFile($this->paginatorTemplate["itemsPerPage"]);
		$this->template->render();
	}

	/** ******************** */

	/**
	 * @return Nette\Application\UI\Form
	 */
	protected function createComponentItemsPerPage(): Nette\Application\UI\Form {
		$form = new Application\UI\Form;
		$form->setTranslator($this->translator);

		$form->addSelect("itemsPerPage", $this->texts["itemsPerPage"], $this->itemsPerPageList)
			//->setAttribute("onchange", "this.form.submit()")
			->setRequired();

		$form->addSubmit("send", $this->texts["send"]);

		$form->onSuccess[] = function(Application\UI\Form $form, array $values){
			$this->setItemsPerPage($values["itemsPerPage"]);
			if (!$this->presenter->isAjax()) {
				$this->redirect("this");
			}
		};

		return $form;
	}
}
