<?php

namespace App\Services;

use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reverse geocode lat/lon → province_id, commune_id, address, city, region
 * Driver: OpenStreetMap Nominatim (free, rate-limited 1 req/sec)
 */
class GeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/reverse';

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Gọi Nominatim và trả về các field cần cập nhật cho Site.
     * Trả về [] nếu không resolve được.
     */
    public function resolveLocation(float $lat, float $lon): array
    {
        $geo = $this->callNominatim($lat, $lon);
        if (! $geo) {
            return [];
        }

        $result = [
            'country' => $geo['country_code'],
        ];

        // Address string từ road + suburb (không bao gồm tỉnh/huyện)
        $addressParts = array_filter([
            $geo['road'],
            $geo['suburb'] ?? $geo['neighbourhood'] ?? null,
            $geo['district'],
        ]);
        if ($addressParts) {
            $result['address'] = implode(', ', $addressParts);
        }

        // Match province từ state/city trả về bởi Nominatim
        $stateName = $geo['state'] ?? '';
        $province  = $this->matchProvince($stateName);

        if ($province) {
            $result['province_id'] = $province->id;
            $result['region']      = $province->region;

            // District (quận/huyện) từ Nominatim — dùng để build city hierarchy
            $districtName = $geo['district'] ?? null;

            // Match commune từ suburb/neighbourhood
            $communeName = $geo['suburb'] ?? $geo['neighbourhood'] ?? $geo['quarter'] ?? '';
            $commune     = null;
            if ($communeName) {
                $commune = $this->matchCommune($communeName, $province->id);
                if ($commune) {
                    $result['commune_id'] = $commune->id;
                }
            }

            // Build city = "Tỉnh > Quận/Huyện > Phường/Xã"
            $result['city'] = $this->buildCityHierarchy(
                $province->name,
                $districtName,
                $commune?->full_name
            );
        }

        return $result;
    }

    /**
     * Build chuỗi phân cấp: "Hà Nội > Hai Bà Trưng > Phường Bạch Mai"
     * Bỏ qua level nào null/empty.
     */
    public function buildCityHierarchy(?string $province, ?string $district, ?string $commune): string
    {
        return implode(' > ', array_filter([
            $province,
            $district,
            $commune,
        ]));
    }

    /**
     * Trả về raw data từ Nominatim (dùng cho debug/preview).
     */
    public function rawGeocode(float $lat, float $lon): ?array
    {
        return $this->callNominatim($lat, $lon);
    }

    // ── Private helpers ─────────────────────────────────────────────────────────

    private function callNominatim(float $lat, float $lon): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'OOHX/1.0 (hi@oohx.net)'])
                ->get(self::NOMINATIM_URL, [
                    'lat'             => $lat,
                    'lon'             => $lon,
                    'format'          => 'json',
                    'accept-language' => 'vi',
                    'addressdetails'  => 1,
                ]);

            if (! $response->ok()) {
                return null;
            }

            $data    = $response->json();
            $address = $data['address'] ?? [];

            return [
                'display_name' => $data['display_name'] ?? null,
                'road'         => $address['road'] ?? $address['pedestrian'] ?? $address['path'] ?? null,
                'suburb'       => $address['suburb'] ?? $address['quarter'] ?? $address['neighbourhood'] ?? null,
                'district'     => $address['city_district'] ?? $address['county'] ?? null,
                // Province: ưu tiên state, fallback city (một số tỉnh Nominatim trả về city)
                'state'        => $address['state'] ?? $address['city'] ?? null,
                'postcode'     => $address['postcode'] ?? null,
                'country_code' => strtoupper($address['country_code'] ?? 'VN'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Khớp tên province từ Nominatim với vietnam_provinces.
     * Tên Nominatim thường là full_name (VD: "Thành phố Hà Nội").
     */
    private function matchProvince(string $name): ?VietnamProvince
    {
        if (! $name) {
            return null;
        }

        $norm = $this->normalize($name);

        // Load tất cả vào bộ nhớ (63 tỉnh, nhỏ)
        return VietnamProvince::all()->first(function (VietnamProvince $p) use ($norm) {
            // So khớp exact (sau normalize)
            if ($this->normalize($p->full_name) === $norm) {
                return true;
            }
            if ($this->normalize($p->name) === $norm) {
                return true;
            }

            // So khớp ASCII (bỏ dấu) để handle encoding issues
            $normAscii = $this->ascii($norm);
            if ($this->ascii($this->normalize($p->name)) === $normAscii) {
                return true;
            }
            if ($this->ascii($this->normalize($p->full_name)) === $normAscii) {
                return true;
            }

            // Contains: "hà nội" trong "thành phố hà nội"
            if (str_contains($this->normalize($p->full_name), $norm)) {
                return true;
            }

            return false;
        });
    }

    /**
     * Khớp tên commune với vietnam_communes (đã lọc theo province_id).
     */
    private function matchCommune(string $name, int $provinceId): ?VietnamCommune
    {
        if (! $name) {
            return null;
        }

        $norm     = $this->normalize($name);
        $normAscii = $this->ascii($norm);

        return VietnamCommune::where('province_id', $provinceId)
            ->get()
            ->first(function (VietnamCommune $c) use ($norm, $normAscii) {
                if ($this->normalize($c->name) === $norm) {
                    return true;
                }
                if ($this->normalize($c->full_name) === $norm) {
                    return true;
                }
                if ($this->ascii($this->normalize($c->name)) === $normAscii) {
                    return true;
                }
                if (str_contains($this->normalize($c->full_name), $norm)) {
                    return true;
                }

                return false;
            });
    }

    /**
     * Lowercase + strip admin prefix + trim whitespace.
     * "Thành phố Hà Nội" → "hà nội"
     */
    public function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));

        // Bỏ prefix phân cấp hành chính
        $prefixes = [
            'thành phố', 'tỉnh', 'thị xã',
            'quận', 'huyện',
            'phường', 'xã', 'thị trấn',
        ];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix . ' ')) {
                $name = ltrim(substr($name, mb_strlen($prefix)), ' ');
                break;
            }
        }

        return preg_replace('/\s+/', ' ', trim($name));
    }

    /**
     * Chuyển sang ASCII (bỏ dấu) để compare tolerant.
     */
    private function ascii(string $name): string
    {
        return Str::ascii($name);
    }
}
