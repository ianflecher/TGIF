@foreach($items as $category => $categoryItems)
     <h2 class="category-title">{{ $category }}</h2>

      @php
        $groupedByName = $categoryItems->groupBy('name');
    @endphp

    @foreach($groupedByName as $productName => $products)
       


<div class="main-content">
    <div class="table-container">
        <table class="inventory-table">
            <thead>
                <tr>
                   <div class="alert alert-danger p-4 rounded-lg shadow mb-2" 
     style="
         color: #2c5530; 
         font-weight: bold; 
         background: #f8f9fa; 
         border: 1px solid #ccc; 
         overflow: hidden; 
         white-space: nowrap; 
         text-overflow: ellipsis;
         max-width: 300px;   /* limit width */
     ">
    

    {{ $productName }} 
    {{-- Calculate total quantity per product --}}
    @php
        $totalQty = $products->sum('quantity');
    @endphp
    (Total: {{ $totalQty }})
</div>



                    </tr>
                    <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Quantity</th>
                    <th>Description</th>
                    <th>Expiration Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                 @foreach($products as $item)
                    @php
                        $isExpired   = $item->expiration_date && \Carbon\Carbon::parse($item->expiration_date)->isPast();
                        $nearExpired = $item->expiration_date &&
                                       \Carbon\Carbon::parse($item->expiration_date)
                                           ->isBetween(now(), now()->addMonth(1));
                    @endphp
                    <tr class="
                        {{ $isExpired ? 'expired' : '' }}
                        {{ !$isExpired && $nearExpired ? 'near-expired' : '' }}
                        {{ $item->quantity == 0 ? 'out-of-stock' : '' }}
                    ">
                        <td>{{ $item->sku }}</td>
                        <td>{{ $item->name }}</td>
                        <td>
    @if($item->quantity == 0)
        <span class="stock-label out-of-stock-label">Out of Stock</span>
    @elseif($item->quantity <= $item->min_quantity ?? 5) {{-- ✅ When quantity is near out of stock --}}
        {{ $item->quantity }}
        <br>
        <span class="stock-label out-of-stock-label">⚠️ Low Stock</span>
    @else
        {{ $item->quantity }}
    @endif
</td>

                        <td>{{ $item->description }}</td>
                       <td>
    @if($item->expiration_date)
        {{ \Carbon\Carbon::parse($item->expiration_date)->format('Y-m-d') }}
    @else
        N/A
    @endif

    @if($isExpired)
        <div class="text-red-600 font-bold mt-1">
            ❌ Expired
        </div>
    @elseif(!$isExpired && $nearExpired)
        <div class="text-yellow-600 font-bold mt-1">
            ⚠️ Near Expiration
        </div>
    @endif
</td>


                        <td class="action-cell">
                            <a href="{{ route('inventories.edit', $item->id) }}" 
   class="btn-potato px-2 py-1" 
   style="background-color:#f6b93b; color:white;">
   Edit
</a>

                                                        <a href="{{ route('stock-out.create', $item->id) }}" class="btn-potato stock-out" style="background-color:#f6b93b; color:white;">Stock-Out</a>
                                                       

                            @php
    $isExpired = $item->expiration_date && \Carbon\Carbon::parse($item->expiration_date)->isPast();
@endphp

<form action="{{ route('inventories.destroy', $item->id) }}" method="POST" 
      onsubmit="return confirm('Are you sure you want to delete this item?');" style="display:inline;">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn-potato delete-btn" {{ $isExpired ? '' : 'disabled' }}>
        Dispose
    </button>
</form>
@if($item->quantity === 0)
    

    <!-- Request Purchase Button -->
  <form action="{{ route('purchase.orders.create', $item->id) }}" method="POST" style="display:inline;">
        @csrf
        <button type="submit" class="btn-potato px-2 py-1" 
                style="background-color:#f6b93b; color:white;">
            Request Purchase
        </button>
    </form>
@endif




                        </td>
                    </tr>
                @endforeach
            </tbody>

        </table>
    </div>
</div>
@endforeach

@endforeach
