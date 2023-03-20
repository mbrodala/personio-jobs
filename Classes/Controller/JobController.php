<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "personio_jobs".
 *
 * Copyright (C) 2023 Elias Häußler <e.haeussler@familie-redlich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace CPSIT\Typo3PersonioJobs\Controller;

use CPSIT\Typo3PersonioJobs\Cache\CacheManager;
use CPSIT\Typo3PersonioJobs\Configuration\ExtensionConfiguration;
use CPSIT\Typo3PersonioJobs\Domain\Model\Job;
use CPSIT\Typo3PersonioJobs\Domain\Repository\JobRepository;
use CPSIT\Typo3PersonioJobs\PageTitle\JobPageTitleProvider;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * JobController
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
class JobController extends ActionController
{
    protected readonly Uri $apiUrl;

    public function __construct(
        protected readonly JobRepository $jobRepository,
        protected readonly MetaTagManagerRegistry $metaTagManagerRegistry,
        protected readonly JobPageTitleProvider $pageTitleProvider,
        protected readonly CacheManager $cacheManager,
        ExtensionConfiguration $extensionConfiguration,
    ) {
        $this->apiUrl = $extensionConfiguration->getApiUrl();
    }

    public function listAction(): ResponseInterface
    {
        $this->cacheManager->addTag();

        $jobs = $this->jobRepository->findAll();

        $this->view->assign('jobs', $jobs);

        return $this->htmlResponse();
    }

    public function showAction(Job $job): ResponseInterface
    {
        $this->cacheManager->addTag($job);

        $this->overwritePageTitle($job);
        $this->addMetaTags($job);

        $this->view->assign('job', $job);
        $this->view->assign('applyUrl', $this->buildApplyUrl($job));

        return $this->htmlResponse();
    }

    protected function buildApplyUrl(Job $job): string
    {
        $language = $this->request->getAttribute('language')?->getTwoLetterIsoCode();
        $applyUrl = $this->apiUrl
            ->withPath(sprintf('/job/%d', $job->getPersonioId()))
            ->withFragment('apply')
        ;

        if ($language !== null) {
            $applyUrl = $applyUrl->withQuery(sprintf('?language=%s', $language));
        }

        return (string)$applyUrl;
    }

    protected function overwritePageTitle(Job $job): void
    {
        $this->pageTitleProvider->setJob($job);
    }

    protected function addMetaTags(Job $job): void
    {
        $description = trim($this->generateDescription($job));
        $keywords = trim($job->getKeywords());

        // Add description
        if ($description !== '') {
            $this->addMetaTag('description', $description);
            $this->addMetaTag('og:description', $description);
            $this->addMetaTag('twitter:description', $description);
        }

        // Add keywords
        if ($keywords !== '') {
            $this->addMetaTag('keywords', $keywords);
        }
    }

    protected function addMetaTag(string $property, string $content): void
    {
        $metaTagManager = $this->metaTagManagerRegistry->getManagerForProperty($property);
        $metaTagManager->addProperty($property, $content);
    }

    protected function generateDescription(Job $job, int $maxLength = 150): string
    {
        $description = '';

        foreach ($job->getJobDescriptions() as $jobDescription) {
            $rawJobDescription = strip_tags($jobDescription->getBodytext());
            $description .= $rawJobDescription . ' ';

            if (mb_strlen($description) >= $maxLength) {
                break;
            }
        }

        return mb_strimwidth($description, 0, $maxLength, '…');
    }
}
