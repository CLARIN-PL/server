<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_External\Lib\SharePoint;

use Office365\PHP\Client\Runtime\Auth\AuthenticationContext;
use Office365\PHP\Client\Runtime\ClientObject;
use Office365\PHP\Client\Runtime\ClientObjectCollection;
use Office365\PHP\Client\SharePoint\ClientContext;
use Office365\PHP\Client\SharePoint\File;
use Office365\PHP\Client\SharePoint\Folder;
use Office365\PHP\Client\SharePoint\SPList;

class SharePointClient {
	/** @var  ClientContext */
	protected $context;

	/** @var  AuthenticationContext */
	protected $authContext;

	/** @var  SPList */
	protected $documentLibrary;

	/** @var ContextsFactory */
	private $contextsFactory;

	/** @var  string */
	private $sharePointUrl;

	/** @var string[] */
	private $credentials;

	/** @var  string */
	private $documentLibraryTitle;

	public function __construct(ContextsFactory $contextsFactory, $sharePointUrl, array $credentials, $documentLibraryTitle) {
		$this->contextsFactory = $contextsFactory;
		$this->sharePointUrl = $sharePointUrl;
		$this->credentials = $credentials;
		$this->documentLibraryTitle = $documentLibraryTitle;
	}

	/**
	 * @param string $path
	 * @param array $properties
	 * @return File|Folder
	 * @throws \Exception
	 */
	public function fetchFileOrFolder($path, array $properties = null) {
		$fetchFileFunc = function ($path, $props) { return $this->fetchFile($path, $props);};
		$fetchFolderFunc = function ($path, $props) { return $this->fetchFolder($path, $props);};
		$fetchers = [ $fetchFileFunc, $fetchFolderFunc ];
		if(strpos($path, '.') === false) {
			$fetchers = array_reverse($fetchers);
		}

		foreach ($fetchers as $fetchFunction) {
			try {
				$instance = call_user_func_array($fetchFunction, [$path, $properties]);
				return $instance;
			} catch (\Exception $e) {
				if(preg_match('/^The file \/.* does not exist\.$/', $e->getMessage()) !== 1
					&& $e->getMessage() !== 'Unknown Error'
				) {
					$this->createClientContext();
					# Unexpected Exception, pass it on
					throw $e;
				}
			}
			$this->createClientContext();
		}

		# Nothing succeeded, quit with not found
		throw new NotFoundException('File or Folder not found');
	}

	public function fetchFile($relativeServerPath, array $properties = null) {
		$this->ensureConnection();
		$file = $this->context->getWeb()->getFileByServerRelativeUrl($relativeServerPath);
		$this->loadAndExecute($file, $properties);
		return $file;
	}

	public function fetchFolder($relativeServerPath, array $properties = null) {
		$this->ensureConnection();
		$folder = $this->context->getWeb()->getFolderByServerRelativeUrl($relativeServerPath);
		$folderCollection = $folder->getFolders();
		$fileCollection = $folder->getFiles();
		$this->context->load($folder, $properties);
		$this->context->load($folderCollection, null);
		$this->context->load($fileCollection, null);
		$this->context->executeQuery();
		return $folder;
	}

	/**
	 * @param $relativeServerPath
	 * @param null $properties
	 * @return ClientObjectCollection[]
	 */
	public function fetchFolderContents($relativeServerPath, $properties = null, Folder $folder = null) {
		$this->ensureConnection();
		if($folder === null) {
			$folder = $this->context->getWeb()->getFolderByServerRelativeUrl($relativeServerPath);
			$folderCollection = $folder->getFolders();
			$fileCollection = $folder->getFiles();
			$this->context->load($folderCollection, $properties);
			$this->context->load($fileCollection, $properties);
			$this->context->executeQuery();
		} else {
			$folderCollection = $folder->getFolders();
			$fileCollection = $folder->getFiles();
		}

		$collections = ['folders' => $folderCollection, 'files' => $fileCollection];

		foreach ($collections as $collection) {
			foreach ($collection->getData() as $item) {
				/** @var File|Folder $item */
				$fields = $item->getListItemAllFields();
				$this->context->load($fields, ['Id', 'Hidden']);
			}
		}
		$this->context->executeQuery();

		return $collections;
	}

	public function loadAndExecute(ClientObject $object, array $properties = null) {
		$this->context->load($object, $properties);
		$this->context->executeQuery();
	}

	/**
	 * @return SPList
	 * @throws NotFoundException
	 */
	private function getDocumentLibrary() {
		if(!is_null($this->documentLibrary)) {
			return $this->documentLibrary;
		}

		$lists = $this->context->getWeb()->getLists()->filter('Title eq \'' . $this->documentLibraryTitle . '\'')->top(1);
		$this->context->load($lists)->executeQuery();
		if ($lists->getCount() === 1 && $lists->getData()[0] instanceof SPList) {
			$this->documentLibrary = $lists->getData()[0];
			return $this->documentLibrary;
		}

		throw new NotFoundException('List not found');
	}

	/**
	 * Set up necessary contexts for authentication and access to SharePoint
	 *
	 * @throws \InvalidArgumentException
	 */
	private function ensureConnection() {
		if($this->context instanceof ClientContext) {
			return;
		}

		if(!is_string($this->credentials['user']) || empty($this->credentials['user'])) {
			throw new \InvalidArgumentException('No user given');
		}
		if(!is_string($this->credentials['password']) || empty($this->credentials['password'])) {
			throw new \InvalidArgumentException('No password given');
		}
		$this->authContext = $this->contextsFactory->getAuthContext($this->credentials['user'], $this->credentials['password']);
		$this->authContext->AuthType = CURLAUTH_NTLM;		# Basic auth does not work somehow…
		$this->createClientContext();
		# Auth is not triggered yet. This will happen when something is requested from SharePoint (on demand), e.g.:
	}

	/**
	 * (re)creates the sharepoint client context
	 */
	private function createClientContext() {
		$this->context = null;
		$this->context = $this->contextsFactory->getClientContext($this->sharePointUrl, $this->authContext);
	}
}
