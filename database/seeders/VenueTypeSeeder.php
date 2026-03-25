<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenueTypeSeeder extends Seeder
{
    public function run(): void
    {
        $venues = [
            // Transit
            ['category'=>'Transit','subcategory'=>'Airports','venue_type'=>'TRANSIT: Airports: Arrival Hall','description'=>'Locations for meeting passengers arriving on flights'],
            ['category'=>'Transit','subcategory'=>'Airports','venue_type'=>'TRANSIT: Airports: Baggage Claim','description'=>'Locations to retrieve baggage'],
            ['category'=>'Transit','subcategory'=>'Airports','venue_type'=>'TRANSIT: Airports: Departures Hall','description'=>'Location for dropping off passengers leaving on flights'],
            ['category'=>'Transit','subcategory'=>'Airports','venue_type'=>'TRANSIT: Airports: Food Court','description'=>'Location within an airport for food'],
            ['category'=>'Transit','subcategory'=>'Airports','venue_type'=>'TRANSIT: Airports: Gates','description'=>'Location to wait for or embark from a specific plane'],
            ['category'=>'Transit','subcategory'=>'Airports','venue_type'=>'TRANSIT: Airports: Lounges','description'=>'Private places to wait for flights'],
            ['category'=>'Transit','subcategory'=>'Airports','venue_type'=>'TRANSIT: Airports: Shopping Area','description'=>'Retail areas within airport facilities'],
            ['category'=>'Transit','subcategory'=>'Buses','venue_type'=>'TRANSIT: Buses: Bus (Inside)','description'=>'Advertising inside a bus'],
            ['category'=>'Transit','subcategory'=>'Buses','venue_type'=>'TRANSIT: Buses: Bus (Outside)','description'=>'Advertising outside a bus'],
            ['category'=>'Transit','subcategory'=>'Buses','venue_type'=>'TRANSIT: Buses: Terminal','description'=>'Advertising at bus terminals'],
            ['category'=>'Transit','subcategory'=>'Subway','venue_type'=>'TRANSIT: Subway: Subway Train','description'=>'A train that travels primarily underground'],
            ['category'=>'Transit','subcategory'=>'Subway','venue_type'=>'TRANSIT: Subway: Platform','description'=>'Areas to wait for, board, or unboard a subway'],
            ['category'=>'Transit','subcategory'=>'Train Stations','venue_type'=>'TRANSIT: Train Stations: Train','description'=>'A train that travels primarily above ground'],
            ['category'=>'Transit','subcategory'=>'Train Stations','venue_type'=>'TRANSIT: Train Stations: Platform','description'=>'Areas to wait for, board, or unboard a train'],
            ['category'=>'Transit','subcategory'=>'Taxi & Rideshare','venue_type'=>'TRANSIT: Taxi & Rideshare Top','description'=>'Displays on top of taxi and rideshare vehicles'],
            ['category'=>'Transit','subcategory'=>'Taxi & Rideshare','venue_type'=>'TRANSIT: Taxi & Rideshare TV','description'=>'Displays inside taxis visible to back seat passengers'],
            ['category'=>'Transit','subcategory'=>'Ferry','venue_type'=>'TRANSIT: Ferry','description'=>'Advertising displays inside passenger water transport'],
            // Retail
            ['category'=>'Retail','subcategory'=>'Fueling Stations','venue_type'=>'RETAIL: Fueling Stations: Fuel Dispenser','description'=>'A self-service device for dispensing fuel'],
            ['category'=>'Retail','subcategory'=>'Fueling Stations','venue_type'=>'RETAIL: Fueling Stations: Shop','description'=>'Store attached to fueling location'],
            ['category'=>'Retail','subcategory'=>'Grocery','venue_type'=>'RETAIL: Grocery: Shop Entrance','description'=>'Areas near the entrance to a grocery store'],
            ['category'=>'Retail','subcategory'=>'Grocery','venue_type'=>'RETAIL: Grocery: Check Out','description'=>'Areas dedicated to paying for purchased goods'],
            ['category'=>'Retail','subcategory'=>'Grocery','venue_type'=>'RETAIL: Grocery: Aisles','description'=>'Areas dedicated to display or retrieval of goods'],
            ['category'=>'Retail','subcategory'=>'Malls','venue_type'=>'RETAIL: Malls: Concourse','description'=>'A large open area including hallways and escalators'],
            ['category'=>'Retail','subcategory'=>'Malls','venue_type'=>'RETAIL: Malls: Food Court','description'=>'Common area with multiple food vendors'],
            ['category'=>'Retail','subcategory'=>'Malls','venue_type'=>'RETAIL: Malls: Spectacular','description'=>'Large impactful screen at a prime mall location'],
            ['category'=>'Retail','subcategory'=>'Convenience Stores','venue_type'=>'RETAIL: Convenience Stores','description'=>'A store with extended hours in a convenient location'],
            ['category'=>'Retail','subcategory'=>'Pharmacies','venue_type'=>'RETAIL: Pharmacies','description'=>'A store where medicinal drugs are dispensed'],
            ['category'=>'Retail','subcategory'=>'Parking Garages','venue_type'=>'RETAIL: Parking Garages','description'=>'A building where people park their vehicles'],
            ['category'=>'Retail','subcategory'=>'Cannabis Dispensaries','venue_type'=>'RETAIL: Cannabis Dispensaries','description'=>'A store that sells cannabis products'],
            ['category'=>'Retail','subcategory'=>'Liquor Stores','venue_type'=>'RETAIL: Liquor Stores','description'=>'A retail shop that predominantly sells alcoholic beverages'],
            // Outdoor
            ['category'=>'Outdoor','subcategory'=>'Billboards','venue_type'=>'OUTDOOR: Billboards: Roadside','description'=>'Large format displays placed alongside roads'],
            ['category'=>'Outdoor','subcategory'=>'Billboards','venue_type'=>'OUTDOOR: Billboards: Highway','description'=>'Large format displays alongside highways'],
            ['category'=>'Outdoor','subcategory'=>'Urban Panels','venue_type'=>'OUTDOOR: Urban Panels','description'=>'Advertising panels in urban pedestrian areas'],
            // Food & Beverage
            ['category'=>'Food & Beverage','subcategory'=>'Bars','venue_type'=>'FOOD & BEVERAGE: Bars','description'=>'A location that primarily serves alcoholic beverages'],
            ['category'=>'Food & Beverage','subcategory'=>'Casual Dining','venue_type'=>'FOOD & BEVERAGE: Casual Dining','description'=>'A restaurant with table service and moderate prices'],
            ['category'=>'Food & Beverage','subcategory'=>'Coffee Shops','venue_type'=>'FOOD & BEVERAGE: Coffee Shops','description'=>'A cafe primarily serving coffee'],
            ['category'=>'Food & Beverage','subcategory'=>'Fast Food','venue_type'=>'FOOD & BEVERAGE: Fast Food','description'=>'Quick service restaurants'],
            ['category'=>'Food & Beverage','subcategory'=>'Food Courts','venue_type'=>'FOOD & BEVERAGE: Food Courts','description'=>'Common area with multiple food vendors'],
            // Point of Care
            ['category'=>'Point of Care','subcategory'=>'Doctor Offices','venue_type'=>'POINT OF CARE: Doctor Offices','description'=>'A location where patients meet with physicians'],
            ['category'=>'Point of Care','subcategory'=>'Hospitals','venue_type'=>'POINT OF CARE: Hospitals','description'=>'Waiting areas and lobbies in hospitals'],
            ['category'=>'Point of Care','subcategory'=>'Pharmacies','venue_type'=>'POINT OF CARE: Pharmacies','description'=>'A store where medicinal drugs are dispensed'],
            // Residential
            ['category'=>'Residential','subcategory'=>'Apartment Buildings','venue_type'=>'RESIDENTIAL: Apartment Buildings: Lobby','description'=>'Entrance lobbies in apartment buildings'],
            ['category'=>'Residential','subcategory'=>'Apartment Buildings','venue_type'=>'RESIDENTIAL: Apartment Buildings: Elevator','description'=>'Elevators in apartment buildings'],
            // Hotels
            ['category'=>'Hotels','subcategory'=>'Hotels','venue_type'=>'HOTELS: Hotels: Lobby','description'=>'Entrance lobbies in hotels'],
            ['category'=>'Hotels','subcategory'=>'Hotels','venue_type'=>'HOTELS: Hotels: Room','description'=>'In-room displays in hotels'],
            ['category'=>'Hotels','subcategory'=>'Hotels','venue_type'=>'HOTELS: Hotels: Common Areas','description'=>'Shared areas in hotels'],
            // Office
            ['category'=>'Office Buildings','subcategory'=>'Office Buildings','venue_type'=>'OFFICE BUILDINGS: Office Buildings: Lobby','description'=>'Entrance lobbies in office buildings'],
            ['category'=>'Office Buildings','subcategory'=>'Office Buildings','venue_type'=>'OFFICE BUILDINGS: Office Buildings: Elevator','description'=>'Elevators in office buildings'],
            // Entertainment
            ['category'=>'Entertainment','subcategory'=>'Movie Theaters','venue_type'=>'ENTERTAINMENT: Movie Theaters','description'=>'Indoor cinemas'],
            ['category'=>'Entertainment','subcategory'=>'Sports Venues','venue_type'=>'ENTERTAINMENT: Sports Venues','description'=>'Sports stadiums and arenas'],
            ['category'=>'Entertainment','subcategory'=>'Gyms','venue_type'=>'ENTERTAINMENT: Gyms','description'=>'Fitness and gym facilities'],
            // Education
            ['category'=>'Educational','subcategory'=>'Colleges and Universities','venue_type'=>'EDUCATIONAL: Colleges and Universities','description'=>'Higher education campuses'],
            ['category'=>'Educational','subcategory'=>'Primary and Secondary Schools','venue_type'=>'EDUCATIONAL: Primary and Secondary Schools','description'=>'K-12 school facilities'],
        ];

        DB::table('venue_types')->insertOrIgnore(
            array_map(fn($v) => array_merge($v, [
                'hivestack_supported' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]), $venues)
        );

        $this->command->info('Seeded '.count($venues).' venue types (OpenOOH v1.1)');
    }
}
