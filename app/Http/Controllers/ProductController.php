<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    /**
     * GET /products — product listing (new explore)
     */
    public function index(Request $request): View
    {
        $products = $this->productService->getProductsPaginated($request);
        $filters = $this->productService->getFilterAggregates();

        return view('frontpage.products.index', [
            'products' => $products,
            'filters'  => $filters,
        ]);
    }

    /**
     * GET /products/{slug} — product detail
     */
    public function show(string $slug): View
    {
        $product = $this->productService->getProductBySlug($slug);
        abort_unless($product, 404);

        $similar = $this->productService->getSimilarProducts($product);

        return view('frontpage.products.show', [
            'product' => $product,
            'similar' => $similar,
        ]);
    }
}
