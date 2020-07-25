<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2020 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Module;

use Aura\Router\RouterContainer;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\GedcomCode\GedcomCodePedi;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Soundex;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function app;
use function array_search;
use function assert;
use function e;
use function explode;
use function in_array;
use function is_int;
use function key;
use function log;
use function next;
use function redirect;
use function route;
use function strip_tags;
use function stripos;
use function usort;
use function view;

/**
 * Class BranchesListModule
 */
class BranchesListModule extends AbstractModule implements ModuleListInterface, RequestHandlerInterface
{
    use ModuleListTrait;

    protected const ROUTE_URL  = '/tree/{tree}/branches{/surname}';

    /** @var ModuleService */
    protected $module_service;

    /**
     * BranchesListModule constructor.
     *
     * @param ModuleService $module_service
     */
    public function __construct(ModuleService $module_service)
    {
        $this->module_service = $module_service;
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        $router_container = app(RouterContainer::class);
        assert($router_container instanceof RouterContainer);

        $router_container->getMap()
            ->get(static::class, static::ROUTE_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST);
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module/list */
        return I18N::translate('Branches');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Branches” module */
        return I18N::translate('A list of branches of a family.');
    }

    /**
     * CSS class for the URL.
     *
     * @return string
     */
    public function listMenuClass(): string
    {
        return 'menu-branches';
    }

    /**
     * @param Tree    $tree
     * @param mixed[] $parameters
     *
     * @return string
     */
    public function listUrl(Tree $tree, array $parameters = []): string
    {
        $xref = app(ServerRequestInterface::class)->getAttribute('xref', '');

        if ($xref !== '') {
            $individual = Factory::individual()->make($xref, $tree);

            if ($individual instanceof Individual && $individual->canShow()) {
                $parameters['surname'] = $parameters['surname'] ?? $individual->getAllNames()[0]['surn'] ?? null;
            }
        }

        $parameters['tree'] = $tree->name();

        return route(static::class, $parameters);
    }

    /**
     * @return string[]
     */
    public function listUrlAttributes(): array
    {
        return [];
    }

    /**
     * Handle URLs generated by older versions of webtrees
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getPageAction(ServerRequestInterface $request): ResponseInterface
    {
        return redirect($this->listUrl($request->getAttribute('tree'), $request->getQueryParams()));
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $user = $request->getAttribute('user');
        assert($user instanceof UserInterface);

        Auth::checkComponentAccess($this, ModuleListInterface::class, $tree, $user);

        // Convert POST requests into GET requests for pretty URLs.
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            return redirect($this->listUrl($tree, (array) $request->getParsedBody()));
        }

        $surname = (string) $request->getAttribute('surname');

        $params      = $request->getQueryParams();
        $ajax        = $params['ajax'] ?? '';
        $soundex_std = (bool) ($params['soundex_std'] ?? false);
        $soundex_dm  = (bool) ($params['soundex_dm'] ?? false);

        if ($ajax === '1') {
            $this->layout = 'layouts/ajax';

            // Highlight direct-line ancestors of this individual.
            $xref = $tree->getUserPreference($user, User::PREF_TREE_ACCOUNT_XREF);
            $self = Factory::individual()->make($xref, $tree);

            if ($surname !== '') {
                $individuals = $this->loadIndividuals($tree, $surname, $soundex_dm, $soundex_std);
            } else {
                $individuals = [];
            }

            if ($self instanceof Individual) {
                $ancestors = $this->allAncestors($self);
            } else {
                $ancestors = [];
            }

            return $this->viewResponse('modules/branches/list', [
                'branches' => $this->getPatriarchsHtml($tree, $individuals, $ancestors, $surname, $soundex_dm, $soundex_std),
            ]);
        }

        if ($surname !== '') {
            /* I18N: %s is a surname */
            $title = I18N::translate('Branches of the %s family', e($surname));

            $ajax_url = $this->listUrl($tree, $params + ['ajax' => true, 'surname' => $surname]);
        } else {
            /* I18N: Branches of a family tree */
            $title = I18N::translate('Branches');

            $ajax_url = '';
        }

        return $this->viewResponse('branches-page', [
            'ajax_url'    => $ajax_url,
            'soundex_dm'  => $soundex_dm,
            'soundex_std' => $soundex_std,
            'surname'     => $surname,
            'title'       => $title,
            'tree'        => $tree,
        ]);
    }

    /**
     * Find all ancestors of an individual, indexed by the Sosa-Stradonitz number.
     *
     * @param Individual $individual
     *
     * @return Individual[]
     */
    private function allAncestors(Individual $individual): array
    {
        $ancestors = [
            1 => $individual,
        ];

        do {
            $sosa = key($ancestors);

            $family = $ancestors[$sosa]->childFamilies()->first();

            if ($family !== null) {
                if ($family->husband() !== null) {
                    $ancestors[$sosa * 2] = $family->husband();
                }
                if ($family->wife() !== null) {
                    $ancestors[$sosa * 2 + 1] = $family->wife();
                }
            }
        } while (next($ancestors));

        return $ancestors;
    }

    /**
     * Fetch all individuals with a matching surname
     *
     * @param Tree   $tree
     * @param string $surname
     * @param bool   $soundex_dm
     * @param bool   $soundex_std
     *
     * @return Individual[]
     */
    private function loadIndividuals(Tree $tree, string $surname, bool $soundex_dm, bool $soundex_std): array
    {
        $individuals = DB::table('individuals')
            ->join('name', static function (JoinClause $join): void {
                $join
                    ->on('name.n_file', '=', 'individuals.i_file')
                    ->on('name.n_id', '=', 'individuals.i_id');
            })
            ->where('i_file', '=', $tree->id())
            ->where('n_type', '<>', '_MARNM')
            ->where(static function (Builder $query) use ($surname, $soundex_dm, $soundex_std): void {
                $query
                    ->where('n_surn', '=', $surname)
                    ->orWhere('n_surname', '=', $surname);

                if ($soundex_std) {
                    $sdx = Soundex::russell($surname);
                    if ($sdx !== '') {
                        foreach (explode(':', $sdx) as $value) {
                            $query->orWhere('n_soundex_surn_std', 'LIKE', '%' . $value . '%');
                        }
                    }
                }

                if ($soundex_dm) {
                    $sdx = Soundex::daitchMokotoff($surname);
                    if ($sdx !== '') {
                        foreach (explode(':', $sdx) as $value) {
                            $query->orWhere('n_soundex_surn_dm', 'LIKE', '%' . $value . '%');
                        }
                    }
                }
            })
            ->select(['individuals.*'])
            ->distinct()
            ->get()
            ->map(Factory::individual()->mapper($tree))
            ->filter(GedcomRecord::accessFilter())
            ->all();

        usort($individuals, Individual::birthDateComparator());

        return $individuals;
    }

    /**
     * For each individual with no ancestors, list their descendants.
     *
     * @param Tree         $tree
     * @param Individual[] $individuals
     * @param Individual[] $ancestors
     * @param string       $surname
     * @param bool         $soundex_dm
     * @param bool         $soundex_std
     *
     * @return string
     */
    private function getPatriarchsHtml(Tree $tree, array $individuals, array $ancestors, string $surname, bool $soundex_dm, bool $soundex_std): string
    {
        $html = '';
        foreach ($individuals as $individual) {
            foreach ($individual->childFamilies() as $family) {
                foreach ($family->spouses() as $parent) {
                    if (in_array($parent, $individuals, true)) {
                        continue 3;
                    }
                }
            }
            $html .= $this->getDescendantsHtml($tree, $individuals, $ancestors, $surname, $soundex_dm, $soundex_std, $individual, null);
        }

        return $html;
    }

    /**
     * Generate a recursive list of descendants of an individual.
     * If parents are specified, we can also show the pedigree (adopted, etc.).
     *
     * @param Tree         $tree
     * @param Individual[] $individuals
     * @param Individual[] $ancestors
     * @param string       $surname
     * @param bool         $soundex_dm
     * @param bool         $soundex_std
     * @param Individual   $individual
     * @param Family|null  $parents
     *
     * @return string
     */
    private function getDescendantsHtml(Tree $tree, array $individuals, array $ancestors, string $surname, bool $soundex_dm, bool $soundex_std, Individual $individual, Family $parents = null): string
    {
        $module = $this->module_service->findByComponent(ModuleChartInterface::class, $tree, Auth::user())->first(static function (ModuleInterface $module) {
            return $module instanceof RelationshipsChartModule;
        });

        // A person has many names. Select the one that matches the searched surname
        $person_name = '';
        foreach ($individual->getAllNames() as $name) {
            [$surn1] = explode(',', $name['sort']);
            if ($this->surnamesMatch($surn1, $surname, $soundex_std, $soundex_dm)) {
                $person_name = $name['full'];
                break;
            }
        }

        // No matching name? Typically children with a different surname. The branch stops here.
        if ($person_name === '') {
            return '<li title="' . strip_tags($individual->fullName()) . '"><small>' . view('icons/sex', ['sex' => $individual->sex()]) . '</small>…</li>';
        }

        // Is this individual one of our ancestors?
        $sosa = array_search($individual, $ancestors, true);
        if (is_int($sosa) && $module instanceof RelationshipsChartModule) {
            $sosa_class = 'search_hit';
            $sosa_html  = '<a class="small ' . $individual->getBoxStyle() . '" href="' . e($module->chartUrl($individual, ['xref2' => $individuals[1]->xref()])) . '" rel="nofollow" title="' . I18N::translate('Relationships') . '">' . I18N::number($sosa) . '</a>' . self::sosaGeneration($sosa);
        } else {
            $sosa_class = '';
            $sosa_html  = '';
        }

        // Generate HTML for this individual, and all their descendants
        $indi_html = '<small>' . view('icons/sex', ['sex' => $individual->sex()]) . '</small><a class="' . $sosa_class . '" href="' . e($individual->url()) . '">' . $person_name . '</a> ' . $individual->lifespan() . $sosa_html;

        // If this is not a birth pedigree (e.g. an adoption), highlight it
        if ($parents) {
            $pedi = '';
            foreach ($individual->facts(['FAMC']) as $fact) {
                if ($fact->target() === $parents) {
                    $pedi = $fact->attribute('PEDI');
                    break;
                }
            }
            if ($pedi !== '' && $pedi !== 'birth') {
                $indi_html = '<span class="red">' . GedcomCodePedi::getValue($pedi, $individual) . '</span> ' . $indi_html;
            }
        }

        // spouses and children
        $spouse_families = $individual->spouseFamilies()
            ->sort(Family::marriageDateComparator());

        if ($spouse_families->isNotEmpty()) {
            $fam_html = '';
            foreach ($spouse_families as $family) {
                $fam_html .= $indi_html; // Repeat the individual details for each spouse.

                $spouse = $family->spouse($individual);
                if ($spouse instanceof Individual) {
                    $sosa = array_search($spouse, $ancestors, true);
                    if (is_int($sosa) && $module instanceof RelationshipsChartModule) {
                        $sosa_class = 'search_hit';
                        $sosa_html  = '<a class="small ' . $spouse->getBoxStyle() . '" href="' . e($module->chartUrl($individual, ['xref2' => $individuals[1]->xref()])) . '" rel="nofollow" title="' . I18N::translate('Relationships') . '">' . I18N::number($sosa) . '</a>' . self::sosaGeneration($sosa);
                    } else {
                        $sosa_class = '';
                        $sosa_html  = '';
                    }
                    $marriage_year = $family->getMarriageYear();
                    if ($marriage_year) {
                        $fam_html .= ' <a href="' . e($family->url()) . '" title="' . strip_tags($family->getMarriageDate()->display()) . '"><i class="icon-rings"></i>' . $marriage_year . '</a>';
                    } elseif ($family->facts(['MARR'])->isNotEmpty()) {
                        $fam_html .= ' <a href="' . e($family->url()) . '" title="' . I18N::translate('Marriage') . '"><i class="icon-rings"></i></a>';
                    } else {
                        $fam_html .= ' <a href="' . e($family->url()) . '" title="' . I18N::translate('Not married') . '"><i class="icon-rings"></i></a>';
                    }
                    $fam_html .= ' <small>' . view('icons/sex', ['sex' => $spouse->sex()]) . '</small><a class="' . $sosa_class . '" href="' . e($spouse->url()) . '">' . $spouse->fullName() . '</a> ' . $spouse->lifespan() . ' ' . $sosa_html;
                }

                $fam_html .= '<ol>';
                foreach ($family->children() as $child) {
                    $fam_html .= $this->getDescendantsHtml($tree, $individuals, $ancestors, $surname, $soundex_dm, $soundex_std, $child, $family);
                }
                $fam_html .= '</ol>';
            }

            return '<li>' . $fam_html . '</li>';
        }

        // No spouses - just show the individual
        return '<li>' . $indi_html . '</li>';
    }

    /**
     * Do two surnames match?
     *
     * @param string $surname1
     * @param string $surname2
     * @param bool   $soundex_std
     * @param bool   $soundex_dm
     *
     * @return bool
     */
    private function surnamesMatch(string $surname1, string $surname2, bool $soundex_std, bool $soundex_dm): bool
    {
        // One name sounds like another?
        if ($soundex_std && Soundex::compare(Soundex::russell($surname1), Soundex::russell($surname2))) {
            return true;
        }
        if ($soundex_dm && Soundex::compare(Soundex::daitchMokotoff($surname1), Soundex::daitchMokotoff($surname2))) {
            return true;
        }

        // One is a substring of the other.  e.g. Halen / Van Halen
        return stripos($surname1, $surname2) !== false || stripos($surname2, $surname1) !== false;
    }

    /**
     * Convert a SOSA number into a generation number. e.g. 8 = great-grandfather = 3 generations
     *
     * @param int $sosa
     *
     * @return string
     */
    private static function sosaGeneration($sosa): string
    {
        $generation = (int) log($sosa, 2) + 1;

        return '<sup title="' . I18N::translate('Generation') . '">' . $generation . '</sup>';
    }
}