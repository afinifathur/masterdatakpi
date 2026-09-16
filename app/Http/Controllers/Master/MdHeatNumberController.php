<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MdHeatNumber;
use App\Models\MdItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MdHeatNumberController extends Controller
{
    public function searchApi(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = MdHeatNumber::with('item.department')
            ->where(function ($sub) use ($q) {
                $sub->where('heat_number', 'like', "%{$q}%")
                    ->orWhere('traveler_number', 'like', "%{$q}%");
            })
            ->limit(15)
            ->get();

        return response()->json($results->map(function ($hn) {
            return [
                'id' => $hn->id,
                'traveler_number' => $hn->traveler_number,
                'heat_number' => $hn->heat_number,
                'item_name' => $hn->item_name,
                'category' => $hn->item ? ($hn->item->department ? $hn->item->department->name : '-') : '-',
                'department_code' => $hn->item ? $hn->item->department_code : '-',
                'customer' => $hn->customer ?? '-',
                'size' => $hn->size ?? '-',
                'status' => $hn->status,
                'edit_url' => route('master.heat-numbers.edit', $hn->id),
            ];
        }));
    }

    /**
     * Display a listing of daily batches (grouped by heat_date).
     */
    public function index(Request $request)
    {
        $query = MdHeatNumber::query()
            ->select(
                'heat_date',
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(cor_qty) as total_cor_qty')
            )
            ->whereNotNull('heat_date')
            ->groupBy('heat_date')
            ->orderBy('heat_date', 'desc');

        // 🔒 Scoping: REMOVED as per request. All admins can see all heat numbers.
        // This allows cross-department help (e.g. adminbubut uploading for another dept).
        /*
        $user = auth()->user();
        if (!in_array($user->role, ['manager', 'direktur', 'mr'])) {
            $query->whereHas('item', function ($q) use ($user) {
                $q->where('department_code', $user->department_code);
            });
        }
        */

        if ($request->filled('month')) {
            $query->whereMonth('heat_date', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('heat_date', $request->year);
        }

        if ($request->filled('department_code')) {
            $query->whereHas('item', function ($q) use ($request) {
                $q->where('department_code', $request->department_code);
            });
        }

        $dailyBatches = $query->paginate(20)->withQueryString();

        return view('master.heat_numbers.index', compact('dailyBatches'));
    }

    /**
     * Show the details of a specific day's heat numbers.
     */
    public function dailyDetails(Request $request, $date)
    {
        $query = MdHeatNumber::whereDate('heat_date', $date);

        // 🔒 Scoping: REMOVED.
        /*
        $user = auth()->user();
        if (!in_array($user->role, ['manager', 'direktur', 'mr'])) {
            $query->whereHas('item', function ($q) use ($user) {
                $q->where('department_code', $user->department_code);
            });
        }
        */

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('heat_number', 'like', "%{$q}%")
                    ->orWhere('item_code', 'like', "%{$q}%")
                    ->orWhere('item_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department_code')) {
            $query->whereHas('item', function ($q) use ($request) {
                $q->where('department_code', $request->department_code);
            });
        }

        $heatNumbers = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();
        $heatDate = \Carbon\Carbon::parse($date);

        return view('master.heat_numbers.daily_details', compact('heatNumbers', 'heatDate'));
    }

    /**
     * Show the form for creating a new heat number.
     */
    public function create()
    {
        $items = MdItem::active()->orderBy('code')->get();
        return view('master.heat_numbers.create', compact('items'));
    }

    /**
     * Store a newly created heat number.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_produksi' => 'nullable|string|max:50',
            'heat_date' => 'nullable|date',
            'item_code' => 'required|string|exists:md_items,code',
            'heat_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('md_heat_numbers')->where(function ($query) use ($request) {
                    return $query->where('item_code', $request->item_code);
                }),
            ],
            'cor_qty' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        // Auto-fetch item name for denormalization
        $item = MdItem::where('code', $validated['item_code'])->first();
        $validated['item_name'] = $item->name;
        $validated['heat_date'] = $validated['heat_date'] ?? now()->toDateString();

        MdHeatNumber::create($validated);

        return redirect()
            ->route('master.heat-numbers.index')
            ->with('success', 'Heat Number berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified heat number.
     */
    public function edit(MdHeatNumber $heatNumber)
    {
        $items = MdItem::active()->orderBy('code')->get();
        return view('master.heat_numbers.edit', compact('heatNumber', 'items'));
    }

    /**
     * Update the specified heat number in storage.
     */
    public function update(Request $request, MdHeatNumber $heatNumber)
    {
        $validated = $request->validate([
            'kode_produksi' => 'nullable|string|max:50',
            'heat_date' => 'nullable|date',
            'item_code' => 'required|string|exists:md_items,code',
            'heat_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('md_heat_numbers')->ignore($heatNumber->id)->where(function ($query) use ($request) {
                    return $query->where('item_code', $request->item_code);
                }),
            ],
            'cor_qty' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        $item = MdItem::where('code', $validated['item_code'])->first();
        $validated['item_name'] = $item->name;

        $heatNumber->update($validated);

        return redirect()
            ->route('master.heat-numbers.index')
            ->with('success', 'Heat Number berhasil diperbarui.');
    }

    /**
     * Deactivate heat number.
     */
    public function deactivate(MdHeatNumber $heatNumber)
    {
        $heatNumber->update(['status' => 'inactive']);
        return back()->with('success', 'Heat Number dinonaktifkan.');
    }

    /**
     * Activate heat number.
     */
    public function activate($heatNumber)
    {
        $hn = MdHeatNumber::findOrFail($heatNumber);
        $hn->update(['status' => 'active']);

        return back()->with('success', "Heat Number {$hn->heat_number} activated.");
    }

    /**
     * Import Page (Handsontable Grid)
     */
    public function import()
    {
        return view('master.heat_numbers.import');
    }

    /**
     * Bulk Store from Grid
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'heat_date' => 'required|date',
            'data' => 'required|array',
        ]);

        $heatDate = $request->input('heat_date');
        $rows = $request->input('data');
        $successCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            // Basic validation for required fields in the row
            $heat_number = trim($row['heat_number'] ?? '');
            $item_code = trim($row['item_code'] ?? '');
            $cor_qty = $row['cor_qty'] ?? 0;
            $kode_prod = trim($row['kode_produksi'] ?? '');
            $size = trim($row['size'] ?? '');
            $customer = trim($row['customer'] ?? '');
            $line = trim($row['line'] ?? '');

            if (empty($heat_number) || empty($item_code)) {
                // Skip empty rows if any
                if (empty($heat_number) && empty($item_code))
                    continue;
                $errors[] = "Baris " . ($index + 1) . ": Heat Number and Item Code are required.";
                continue;
            }

            // Lookup Item Name
            $item = \App\Models\MdItem::where('code', $item_code)->first();
            if (!$item) {
                $errors[] = "Baris " . ($index + 1) . ": Item Code [{$item_code}] not found.";
                continue;
            }

            // Check for existing Heat Number + Item combination (GLOBAL UNIQUENESS)
            $exists = MdHeatNumber::where('heat_number', $heat_number)
                ->where('item_code', $item->code) // Use $item->code normalized
                ->exists();

            if ($exists) {
                // REJECT DUPLICATES
                $errors[] = "Baris " . ($index + 1) . ": Heat Number '{$heat_number}' dengan Item '{$item_code}' sudah ada di database (Duplicate).";
                continue;
            }

            try {
                MdHeatNumber::create([
                    'heat_number' => $heat_number,
                    'item_code' => $item->code,
                    'heat_date' => $heatDate,
                    'item_name' => $item->name,
                    'cor_qty' => (int) $cor_qty,
                    'kode_produksi' => $kode_prod,
                    'size' => $size ?: null,
                    'customer' => $customer ?: null,
                    'line' => $line ?: null,
                    'status' => 'active',
                ]);
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "Baris " . ($index + 1) . ": Error: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully imported {$successCount} records.",
            'errors' => $errors
        ]);
    }
}
