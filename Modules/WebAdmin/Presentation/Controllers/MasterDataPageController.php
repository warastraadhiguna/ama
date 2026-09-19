<?php

namespace Modules\WebAdmin\Presentation\Controllers;

use App\Models\ActivityType;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Application\AuditLogger;

/**
 * docs section 29.3 (Master Data): activity types, product categories,
 * products, positions, work locations. Deliberately no delete — rows are
 * referenced by activities, plans and users; "retire" a value by turning
 * is_active off (mobile clients only fetch active ones).
 */
class MasterDataPageController extends Controller
{
    /** @var array<string, array{label: string, model: class-string<Model>}> */
    private const RESOURCES = [
        'activity-types' => ['label' => 'Jenis Kegiatan', 'model' => ActivityType::class],
        'product-categories' => ['label' => 'Kategori Produk', 'model' => ProductCategory::class],
        'products' => ['label' => 'Produk', 'model' => Product::class],
        'positions' => ['label' => 'Jabatan', 'model' => Position::class],
        'work-locations' => ['label' => 'Lokasi Tugas', 'model' => WorkLocation::class],
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(string $resource): Response
    {
        $model = $this->modelFor($resource);

        $query = $model::query()->orderBy('name');
        if ($resource === 'products') {
            $query->with('category');
        }

        return Inertia::render('MasterData/Index', [
            'resource' => $resource,
            'tabs' => collect(self::RESOURCES)->map(fn (array $r, string $key) => ['key' => $key, 'label' => $r['label']])->values(),
            'rows' => $query->get()->map(fn (Model $row) => [
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'is_active' => (bool) $row->is_active,
                'product_category_id' => $resource === 'products' ? $row->product_category_id : null,
                'product_category' => $resource === 'products' ? $row->category?->name : null,
            ]),
            'categories' => $resource === 'products'
                ? ProductCategory::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $model = $this->modelFor($resource);
        $data = $request->validate($this->rules($resource, $model));

        $record = $model::create($data);
        $this->auditLogger->log($request->user(), 'created', $model, $record->id, newValues: $data);

        return back()->with('success', 'Data ditambahkan.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $model = $this->modelFor($resource);
        $record = $model::findOrFail($id);
        $data = $request->validate($this->rules($resource, $model, $record->id));

        $old = $record->only(array_keys($data));
        $record->update($data);
        $this->auditLogger->log($request->user(), 'updated', $model, $record->id, $old, $data);

        return back()->with('success', 'Data diperbarui.');
    }

    /**
     * @return class-string<Model>
     */
    private function modelFor(string $resource): string
    {
        return (self::RESOURCES[$resource] ?? abort(404))['model'];
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, mixed>
     */
    private function rules(string $resource, string $model, ?int $ignoreId = null): array
    {
        $table = (new $model)->getTable();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique($table, 'code')->ignore($ignoreId)],
            'is_active' => ['required', 'boolean'],
        ];

        if ($resource === 'products') {
            $rules['product_category_id'] = ['required', 'integer', 'exists:product_categories,id'];
        }

        return $rules;
    }
}
