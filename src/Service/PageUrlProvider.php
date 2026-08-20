<?php

declare(strict_types=1);

/*
 * Local Fonts
 *
 * Package: vtinnovations/localfonts
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

namespace VTinnovations\LocalFonts\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Doctrine\DBAL\Connection;

final class PageUrlProvider
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getPublishedUrls(): array
    {
        $this->framework->initialize();

        $now = time();
        $pageIds = $this->connection->fetchFirstColumn(
            "SELECT id FROM tl_page WHERE type = 'regular' AND published = '1' AND (start = '' OR start <= ?) AND (stop = '' OR stop > ?) ORDER BY sorting",
            [$now, $now]
        );
        $adapter = $this->framework->getAdapter(PageModel::class);
        $urls = [];

        foreach ($pageIds as $pageId) {
            $page = $adapter->findByPk((int) $pageId);

            if (null !== $page) {
                $urls[] = $this->buildUrl($page);
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * getAbsoluteUrl() resolves the host from the current request, so on the
     * command line (cron, contao-console) every page ends up on "localhost".
     * That silently crawls the wrong site root on multi-domain installs, so the
     * root page's configured DNS entry wins whenever it is set.
     */
    private function buildUrl(PageModel $page): string
    {
        $url = (string) $page->getAbsoluteUrl();

        $page->loadDetails();
        $domain = trim((string) $page->domain);

        if ('' === $domain) {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $scheme = $page->rootUseSSL ? 'https' : 'http';

        return $scheme . '://' . $domain . $path . $query;
    }
}
