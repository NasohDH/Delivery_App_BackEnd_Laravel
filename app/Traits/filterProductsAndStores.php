<?php

namespace App\Traits;

use Carbon\Carbon;

trait filterProductsAndStores
{
    public function filterProductsAndStores($request, $productsQuery = null, $storesQuery = null)
    {
        $key = $request->get('key', '');

        if ($productsQuery && !empty($key)) {
            $productsQuery->where('name', 'LIKE', '%' . $key . '%')
                ->with(['mainImage:product_id,path', 'store:id,name'])
                ->select('id', 'name', 'price', 'store_id', 'created_at');
        }

        if ($storesQuery && !empty($key)) {
            $storesQuery->where('name', 'LIKE', '%' . $key . '%');
        }

        if ($country = $request->get('country')) {
            if ($storesQuery) {
                $storesQuery->where('location->country', $country);
            }

            if ($productsQuery) {
                $productsQuery->whereHas('store', function ($query) use ($country) {
                    $query->where('location->country', $country);
                });
            }
        }

        if ($store = $request->get('store')) {
            if ($productsQuery) {
                $productsQuery->whereHas('store', function ($query) use ($store) {
                    $query->where('name', $store);
                });
            }
        }

        if ($city = $request->get('city')) {
            if ($storesQuery) {
                $storesQuery->where('location->city', $city);
            }

            if ($productsQuery) {
                $productsQuery->whereHas('store', function ($query) use ($city) {
                    $query->where('location->city', $city);
                });
            }
        }

        if ($minPrice = $request->get('min_price')) {
            if ($productsQuery) {
                $productsQuery->where('price', '>=', $minPrice);
            }
        }

        if ($maxPrice = $request->get('max_price')) {
            if ($productsQuery) {
                $productsQuery->where('price', '<=', $maxPrice);
            }
        }
        if ($categoryName = $request->get('category')) {
            if ($productsQuery) {
                $productsQuery->whereHas('categories', function ($query) use ($categoryName) {
                    $query->where('categories.name', $categoryName);
                });
            }
        }
        if ($date = $request->get('date')) {
            if ($productsQuery) {
                if ($date == 'last-day') {
                    $productsQuery->whereDate('created_at', '>=', Carbon::yesterday());
                } elseif ($date == 'last-week') {
                    $productsQuery->whereDate('created_at', '>=', Carbon::now()->subDays(7));
                } elseif ($date == 'last-month') {
                    $productsQuery->whereDate('created_at', '>=', Carbon::now()->subDays(30));
                }
            }
        }

        return compact('productsQuery', 'storesQuery');
    }}
