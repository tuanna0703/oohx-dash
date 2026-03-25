<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds venue_types table with OpenOOH Venue Taxonomy v1.1
 * Source: https://github.com/openooh/venue-taxonomy
 *
 * Run: php artisan db:seed --class=OpenOOHVenueTypeSeeder
 */
class OpenOOHVenueTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: Insert all rows with enumeration_id (parent_id resolved in step 2)
        $venues = $this->getVenueData();

        DB::table('venue_types')->truncate();

        // Insert without parent_id first
        foreach ($venues as $v) {
            DB::table('venue_types')->insertOrIgnore([
                'enumeration_id'    => $v['enumeration_id'],
                'string_value'      => $v['string_value'],
                'category'          => $v['category'],
                'subcategory'       => $v['subcategory'],
                'venue_type'        => $v['venue_type'],
                'description'       => $v['description'],
                'depth'             => $v['depth'],
                'hivestack_supported' => true,
                'is_active'         => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        // Step 2: Resolve parent_id from parent_enum_id
        foreach ($venues as $v) {
            if ($v['parent_enum_id'] !== null) {
                $parent = DB::table('venue_types')
                    ->where('enumeration_id', $v['parent_enum_id'])
                    ->value('id');

                if ($parent) {
                    DB::table('venue_types')
                        ->where('enumeration_id', $v['enumeration_id'])
                        ->update(['parent_id' => $parent]);
                }
            }
        }

        $total = DB::table('venue_types')->count();
        $this->command->info("✅ Seeded {$total} venue types (OpenOOH Taxonomy v1.1)");
    }

    private function getVenueData(): array
    {
        return [
            // ── Transit ───────────────────────────────────────────────────────
            ['enumeration_id'=>1,'string_value'=>'transit','category'=>'Transit','subcategory'=>'','venue_type'=>'Transit','description'=>'Transit venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>101,'string_value'=>'transit.airports','category'=>'Transit','subcategory'=>'Airports','venue_type'=>'Transit : Airports','description'=>'Signage located throughout terminals in arrival and departure areas, ticketing areas, baggage claim, gate-hold rooms, concourses, retail shops, and VIP lounges.','depth'=>1,'parent_enum_id'=>1],
            ['enumeration_id'=>10101,'string_value'=>'transit.airports.arrivals_hall','category'=>'Transit','subcategory'=>'Airports','venue_type'=>'Transit : Airports : Arrival Hall','description'=>'Locations for meeting passengers arriving on flights','depth'=>2,'parent_enum_id'=>101],
            ['enumeration_id'=>10102,'string_value'=>'transit.airports.baggage_claim','category'=>'Transit','subcategory'=>'Airports','venue_type'=>'Transit : Airports : Baggage Claim','description'=>'Locations to retrieve baggage not carried during a flight','depth'=>2,'parent_enum_id'=>101],
            ['enumeration_id'=>10103,'string_value'=>'transit.airports.departures_hall','category'=>'Transit','subcategory'=>'Airports','venue_type'=>'Transit : Airports : Departures Hall','description'=>'Location for dropping off passengers leaving on flights','depth'=>2,'parent_enum_id'=>101],
            ['enumeration_id'=>10104,'string_value'=>'transit.airports.food_court','category'=>'Transit','subcategory'=>'Airports','venue_type'=>'Transit : Airports : Food Court','description'=>'Location within an airport for food, typically casual','depth'=>2,'parent_enum_id'=>101],
            ['enumeration_id'=>10105,'string_value'=>'transit.airports.gates','category'=>'Transit','subcategory'=>'Airports','venue_type'=>'Transit : Airports : Gates','description'=>'Location to wait for or embark or disembark from a specific plane','depth'=>2,'parent_enum_id'=>101],
            ['enumeration_id'=>10106,'string_value'=>'transit.airports.lounges','category'=>'Transit','subcategory'=>'Airports','venue_type'=>'Transit : Airports : Lounges','description'=>'(typically private) places to wait for flights','depth'=>2,'parent_enum_id'=>101],
            ['enumeration_id'=>10107,'string_value'=>'transit.airports.shopping_area','category'=>'Transit','subcategory'=>'Airports','venue_type'=>'Transit : Airports : Shopping Area','description'=>'Retail areas contained within airport facilities','depth'=>2,'parent_enum_id'=>101],
            ['enumeration_id'=>102,'string_value'=>'transit.buses','category'=>'Transit','subcategory'=>'Buses','venue_type'=>'Transit : Buses','description'=>'Displays located on or in city or intercity buses.','depth'=>1,'parent_enum_id'=>1],
            ['enumeration_id'=>10201,'string_value'=>'transit.buses.bus','category'=>'Transit','subcategory'=>'Buses','venue_type'=>'Transit : Buses : Bus (Inside)','description'=>'Advertising inside a bus, primarily visible to bus passengers','depth'=>2,'parent_enum_id'=>102],
            ['enumeration_id'=>10202,'string_value'=>'transit.buses.terminal','category'=>'Transit','subcategory'=>'Buses','venue_type'=>'Transit : Buses : Terminal','description'=>'Advertising at facilities for embarking or disembarking from a bus','depth'=>2,'parent_enum_id'=>102],
            ['enumeration_id'=>10203,'string_value'=>'transit.buses.bus_outside','category'=>'Transit','subcategory'=>'Buses','venue_type'=>'Transit : Buses : Bus (Outside)','description'=>'Advertising outside a bus, primarily visible to people not riding the bus','depth'=>2,'parent_enum_id'=>102],
            ['enumeration_id'=>103,'string_value'=>'transit.taxi_rideshare_tv','category'=>'Transit','subcategory'=>'Taxi & Rideshare TV','venue_type'=>'Transit : Taxi & Rideshare TV','description'=>'Advertising displays placed inside taxis and rideshare vehicles visible to back seat passengers.','depth'=>1,'parent_enum_id'=>1],
            ['enumeration_id'=>104,'string_value'=>'transit.taxi_rideshare_top','category'=>'Transit','subcategory'=>'Taxi & Rideshare Top','venue_type'=>'Transit : Taxi & Rideshare Top','description'=>'Advertising displays placed on top of taxi and rideshare vehicles.','depth'=>1,'parent_enum_id'=>1],
            ['enumeration_id'=>105,'string_value'=>'transit.subway','category'=>'Transit','subcategory'=>'Subway','venue_type'=>'Transit : Subway','description'=>'Advertising displays placed inside subway trains or stations or on platforms.','depth'=>1,'parent_enum_id'=>1],
            ['enumeration_id'=>10501,'string_value'=>'transit.subway.train','category'=>'Transit','subcategory'=>'Subway','venue_type'=>'Transit : Subway : Subway Train','description'=>'A train that travels primarily underground','depth'=>2,'parent_enum_id'=>105],
            ['enumeration_id'=>10502,'string_value'=>'transit.subway.platform','category'=>'Transit','subcategory'=>'Subway','venue_type'=>'Transit : Subway : Platform','description'=>'Areas to wait for, board, or unboard a subway','depth'=>2,'parent_enum_id'=>105],
            ['enumeration_id'=>106,'string_value'=>'transit.train_stations','category'=>'Transit','subcategory'=>'Train Stations','venue_type'=>'Transit : Train Stations','description'=>'Advertising displays placed inside train stations or on platforms.','depth'=>1,'parent_enum_id'=>1],
            ['enumeration_id'=>10601,'string_value'=>'transit.train_stations.train','category'=>'Transit','subcategory'=>'Train Stations','venue_type'=>'Transit : Train Stations : Train','description'=>'A train that travels primarily above ground, on rails','depth'=>2,'parent_enum_id'=>106],
            ['enumeration_id'=>10602,'string_value'=>'transit.train_stations.platform','category'=>'Transit','subcategory'=>'Train Stations','venue_type'=>'Transit : Train Stations : Platform','description'=>'Areas to wait for, board, or unboard a train','depth'=>2,'parent_enum_id'=>106],
            ['enumeration_id'=>107,'string_value'=>'transit.ferry','category'=>'Transit','subcategory'=>'Ferry','venue_type'=>'Transit : Ferry','description'=>'Advertising displays placed inside a passenger water transport.','depth'=>1,'parent_enum_id'=>1],

            // ── Retail ────────────────────────────────────────────────────────
            ['enumeration_id'=>2,'string_value'=>'retail','category'=>'Retail','subcategory'=>'','venue_type'=>'Retail','description'=>'Retail venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>201,'string_value'=>'retail.gas_stations','category'=>'Retail','subcategory'=>'Fueling Stations','venue_type'=>'Retail : Fueling Stations','description'=>'An establishment beside a road selling fuel for motor vehicles.','depth'=>1,'parent_enum_id'=>2],
            ['enumeration_id'=>20101,'string_value'=>'retail.gas_stations.pump','category'=>'Retail','subcategory'=>'Fueling Stations','venue_type'=>'Retail : Fueling Stations : Fuel Dispenser','description'=>'A self-service device for dispensing fuel to vehicles.','depth'=>2,'parent_enum_id'=>201],
            ['enumeration_id'=>20102,'string_value'=>'retail.gas_stations.shop','category'=>'Retail','subcategory'=>'Fueling Stations','venue_type'=>'Retail : Fueling Stations : Shop','description'=>'A store attached to a fueling location','depth'=>2,'parent_enum_id'=>201],
            ['enumeration_id'=>202,'string_value'=>'retail.convenience_store','category'=>'Retail','subcategory'=>'Convenience Stores','venue_type'=>'Retail : Convenience Stores','description'=>'A store with extended opening hours in a convenient location.','depth'=>1,'parent_enum_id'=>2],
            ['enumeration_id'=>203,'string_value'=>'retail.grocery','category'=>'Retail','subcategory'=>'Grocery','venue_type'=>'Retail : Grocery','description'=>'A retail shop that primarily sells food, either fresh or preserved.','depth'=>1,'parent_enum_id'=>2],
            ['enumeration_id'=>20301,'string_value'=>'retail.grocery.shop_entrance','category'=>'Retail','subcategory'=>'Grocery','venue_type'=>'Retail : Grocery : Shop Entrance','description'=>'Areas near the entrance to a store','depth'=>2,'parent_enum_id'=>203],
            ['enumeration_id'=>20302,'string_value'=>'retail.grocery.check_out','category'=>'Retail','subcategory'=>'Grocery','venue_type'=>'Retail : Grocery : Check Out','description'=>'Areas primarily dedicated to paying for purchased goods','depth'=>2,'parent_enum_id'=>203],
            ['enumeration_id'=>20303,'string_value'=>'retail.grocery.aisles','category'=>'Retail','subcategory'=>'Grocery','venue_type'=>'Retail : Grocery : Aisles','description'=>'Areas primarily dedicated to the display or retrieval of goods','depth'=>2,'parent_enum_id'=>203],
            ['enumeration_id'=>204,'string_value'=>'retail.liquor_stores','category'=>'Retail','subcategory'=>'Liquor Stores','venue_type'=>'Retail : Liquor Stores','description'=>'A retail shop that predominantly sells prepackaged alcoholic beverages.','depth'=>1,'parent_enum_id'=>2],
            ['enumeration_id'=>205,'string_value'=>'retail.malls','category'=>'Retail','subcategory'=>'Mall','venue_type'=>'Retail : Mall','description'=>'A large building containing a variety of retail stores and restaurants.','depth'=>1,'parent_enum_id'=>2],
            ['enumeration_id'=>20501,'string_value'=>'retail.malls.concourse','category'=>'Retail','subcategory'=>'Mall','venue_type'=>'Retail : Mall : Concourse','description'=>'A large open area including hallways and escalators','depth'=>2,'parent_enum_id'=>205],
            ['enumeration_id'=>20502,'string_value'=>'retail.malls.food_court','category'=>'Retail','subcategory'=>'Mall','venue_type'=>'Retail : Mall : Food Court','description'=>'A common area with multiple food vendors and common tables.','depth'=>2,'parent_enum_id'=>205],
            ['enumeration_id'=>20503,'string_value'=>'retail.malls.spectacular','category'=>'Retail','subcategory'=>'Mall','venue_type'=>'Retail : Mall : Spectacular','description'=>'Large and impactful screen at a prime mall location.','depth'=>2,'parent_enum_id'=>205],
            ['enumeration_id'=>206,'string_value'=>'retail.dispensaries','category'=>'Retail','subcategory'=>'Cannabis Dispensaries','venue_type'=>'Retail : Cannabis Dispensaries','description'=>'A store that sells and dispenses cannabis and CBD products.','depth'=>1,'parent_enum_id'=>2],
            ['enumeration_id'=>207,'string_value'=>'retail.pharmacies','category'=>'Retail','subcategory'=>'Pharmacies','venue_type'=>'Retail : Pharmacies','description'=>'A store where medicinal drugs are dispensed and sold.','depth'=>1,'parent_enum_id'=>2],
            ['enumeration_id'=>208,'string_value'=>'retail.parking_garages','category'=>'Retail','subcategory'=>'Parking Garages','venue_type'=>'Retail : Parking Garages','description'=>'A building in which people pay to park their vehicles.','depth'=>1,'parent_enum_id'=>2],

            // ── Outdoor ───────────────────────────────────────────────────────
            ['enumeration_id'=>3,'string_value'=>'outdoor','category'=>'Outdoor','subcategory'=>'','venue_type'=>'Outdoor','description'=>'Outdoor venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>301,'string_value'=>'outdoor.billboards','category'=>'Outdoor','subcategory'=>'Billboards','venue_type'=>'Outdoor : Billboards','description'=>'Located primarily on major roads, attracting high-density consumer exposure.','depth'=>1,'parent_enum_id'=>3],
            ['enumeration_id'=>30101,'string_value'=>'outdoor.billboards.roadside','category'=>'Outdoor','subcategory'=>'Billboards','venue_type'=>'Outdoor : Billboards : Roadside','description'=>'Primarily vehicular environments.','depth'=>2,'parent_enum_id'=>301],
            ['enumeration_id'=>30102,'string_value'=>'outdoor.billboards.highway','category'=>'Outdoor','subcategory'=>'Billboards','venue_type'=>'Outdoor : Billboards : Highway','description'=>'High-speed vehicular environments with controlled entrance/exit.','depth'=>2,'parent_enum_id'=>301],
            ['enumeration_id'=>30103,'string_value'=>'outdoor.billboards.spectacular','category'=>'Outdoor','subcategory'=>'Billboards','venue_type'=>'Outdoor : Billboards : Spectacular','description'=>'A bulletin positioned at a prime location, often with special embellishments.','depth'=>2,'parent_enum_id'=>301],
            ['enumeration_id'=>302,'string_value'=>'outdoor.urban_panels','category'=>'Outdoor','subcategory'=>'Urban Panels','venue_type'=>'Outdoor : Urban Panels','description'=>'Digital screens in urban environments, typically providing a public amenity.','depth'=>1,'parent_enum_id'=>3],
            ['enumeration_id'=>303,'string_value'=>'outdoor.bus_shelters','category'=>'Outdoor','subcategory'=>'Bus Shelters','venue_type'=>'Outdoor : Bus Shelters','description'=>'Enclosures where individuals may wait for buses in an urban environment.','depth'=>1,'parent_enum_id'=>3],

            // ── Health & Beauty ───────────────────────────────────────────────
            ['enumeration_id'=>4,'string_value'=>'health_beauty','category'=>'Health & Beauty','subcategory'=>'','venue_type'=>'Health & Beauty','description'=>'Health and beauty venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>401,'string_value'=>'health_beauty.gyms','category'=>'Health & Beauty','subcategory'=>'Gyms','venue_type'=>'Health & Beauty : Gyms','description'=>'A club or building containing equipment for physical exercise.','depth'=>1,'parent_enum_id'=>4],
            ['enumeration_id'=>40101,'string_value'=>'health_beauty.gyms.lobby','category'=>'Health & Beauty','subcategory'=>'Gyms','venue_type'=>'Health & Beauty : Gyms : Lobby','description'=>'Area for waiting or meeting guests','depth'=>2,'parent_enum_id'=>401],
            ['enumeration_id'=>40102,'string_value'=>'health_beauty.gyms.equipment','category'=>'Health & Beauty','subcategory'=>'Gyms','venue_type'=>'Health & Beauty : Gyms : Fitness Equipment','description'=>'Area primarily for exercise or the usage of fitness equipment','depth'=>2,'parent_enum_id'=>401],
            ['enumeration_id'=>402,'string_value'=>'health_beauty.salons','category'=>'Health & Beauty','subcategory'=>'Salons','venue_type'=>'Health & Beauty : Salons','description'=>'An establishment where a hairdresser, beautician, or couturier conducts business.','depth'=>1,'parent_enum_id'=>4],
            ['enumeration_id'=>40201,'string_value'=>'health_beauty.salons.unisex','category'=>'Health & Beauty','subcategory'=>'Salons','venue_type'=>'Health & Beauty : Salons : Unisex Salon','description'=>'Salon catering to clients of any sex','depth'=>2,'parent_enum_id'=>402],
            ['enumeration_id'=>40202,'string_value'=>'health_beauty.salons.mens','category'=>'Health & Beauty','subcategory'=>'Salons','venue_type'=>'Health & Beauty : Salons : Men\'s Salon','description'=>'Salon primarily catering towards men','depth'=>2,'parent_enum_id'=>402],
            ['enumeration_id'=>40203,'string_value'=>'health_beauty.salons.womens','category'=>'Health & Beauty','subcategory'=>'Salons','venue_type'=>'Health & Beauty : Salons : Women\'s Salon','description'=>'Salon primarily catering towards women','depth'=>2,'parent_enum_id'=>402],
            ['enumeration_id'=>403,'string_value'=>'health_beauty.spas','category'=>'Health & Beauty','subcategory'=>'Spas','venue_type'=>'Health & Beauty : Spas','description'=>'A commercial establishment offering health and beauty treatment.','depth'=>1,'parent_enum_id'=>4],

            // ── Point of Care ─────────────────────────────────────────────────
            ['enumeration_id'=>5,'string_value'=>'point_care','category'=>'Point of Care','subcategory'=>'','venue_type'=>'Point of Care','description'=>'Point of care venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>501,'string_value'=>'point_care.doctor_offices','category'=>'Point of Care','subcategory'=>'Doctor\'s Offices','venue_type'=>'Point of Care : Doctor\'s Offices','description'=>'Non-hospital facility run by a physician for treatment of people.','depth'=>1,'parent_enum_id'=>5],
            ['enumeration_id'=>502,'string_value'=>'point_care.veterinary_offices','category'=>'Point of Care','subcategory'=>'Veterinary Offices','venue_type'=>'Point of Care : Veterinary Offices','description'=>'Non-hospital facility run by a veterinarian for treatment of animals.','depth'=>1,'parent_enum_id'=>5],

            // ── Education ─────────────────────────────────────────────────────
            ['enumeration_id'=>6,'string_value'=>'education','category'=>'Education','subcategory'=>'','venue_type'=>'Education','description'=>'Education venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>601,'string_value'=>'education.schools','category'=>'Education','subcategory'=>'Schools','venue_type'=>'Education : Schools','description'=>'An educational institution for K-12 students.','depth'=>1,'parent_enum_id'=>6],
            ['enumeration_id'=>602,'string_value'=>'education.colleges','category'=>'Education','subcategory'=>'Colleges and Universities','venue_type'=>'Education : Colleges and Universities','description'=>'An education institution for advanced learning, conferring degrees.','depth'=>1,'parent_enum_id'=>6],
            ['enumeration_id'=>60201,'string_value'=>'education.colleges.residences','category'=>'Education','subcategory'=>'Colleges and Universities','venue_type'=>'Education : Colleges and Universities : Residences','description'=>'Places where faculty or students live','depth'=>2,'parent_enum_id'=>602],
            ['enumeration_id'=>60202,'string_value'=>'education.colleges.common','category'=>'Education','subcategory'=>'Colleges and Universities','venue_type'=>'Education : Colleges and Universities : Common Areas','description'=>'Shared spaces for study, dining, or leisure activities','depth'=>2,'parent_enum_id'=>602],
            ['enumeration_id'=>60203,'string_value'=>'education.colleges.athletics','category'=>'Education','subcategory'=>'Colleges and Universities','venue_type'=>'Education : Colleges and Universities : Athletic Facilities','description'=>'Facilities or stadiums for sporting competition','depth'=>2,'parent_enum_id'=>602],

            // ── Office Buildings ──────────────────────────────────────────────
            ['enumeration_id'=>7,'string_value'=>'office_buildings','category'=>'Office Buildings','subcategory'=>'','venue_type'=>'Office Buildings','description'=>'Office building venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>701,'string_value'=>'office_buildings.office_buildings','category'=>'Office Buildings','subcategory'=>'Office Buildings','venue_type'=>'Office Buildings : Office Buildings','description'=>'Commercial buildings containing spaces designed for offices.','depth'=>1,'parent_enum_id'=>7],
            ['enumeration_id'=>70101,'string_value'=>'office_buildings.office_buildings.elevator','category'=>'Office Buildings','subcategory'=>'Office Buildings','venue_type'=>'Office Buildings : Office Buildings : Elevator','description'=>'Enclosed vertical conveyance for people and goods','depth'=>2,'parent_enum_id'=>701],
            ['enumeration_id'=>70102,'string_value'=>'office_buildings.office_buildings.lobby','category'=>'Office Buildings','subcategory'=>'Office Buildings','venue_type'=>'Office Buildings : Office Buildings : Lobby','description'=>'Common space for tenants to meet and greet visitors, typically near entrances','depth'=>2,'parent_enum_id'=>701],

            // ── Leisure ───────────────────────────────────────────────────────
            ['enumeration_id'=>8,'string_value'=>'entertainment','category'=>'Leisure','subcategory'=>'','venue_type'=>'Leisure','description'=>'Leisure and entertainment venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>801,'string_value'=>'entertainment.recreational','category'=>'Leisure','subcategory'=>'Recreational Locations','venue_type'=>'Leisure : Recreational Locations','description'=>'Location where recreational and/or leisure activities take place.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>80101,'string_value'=>'entertainment.recreational.theme_parks','category'=>'Leisure','subcategory'=>'Recreational Locations','venue_type'=>'Leisure : Recreational Locations : Theme Parks','description'=>'An amusement park with a unifying setting or idea.','depth'=>2,'parent_enum_id'=>801],
            ['enumeration_id'=>80102,'string_value'=>'entertainment.recreational.museums_galleries','category'=>'Leisure','subcategory'=>'Recreational Locations','venue_type'=>'Leisure : Recreational Locations : Museums and Galleries','description'=>'A building in which objects of historical, scientific, artistic, or cultural interest are stored and exhibited.','depth'=>2,'parent_enum_id'=>801],
            ['enumeration_id'=>80103,'string_value'=>'entertainment.recreational.concert_venues','category'=>'Leisure','subcategory'=>'Recreational Locations','venue_type'=>'Leisure : Recreational Locations : Concert Venues','description'=>'Any location used for a concert or musical performance','depth'=>2,'parent_enum_id'=>801],
            ['enumeration_id'=>802,'string_value'=>'entertainment.movie_theaters','category'=>'Leisure','subcategory'=>'Movie Theaters','venue_type'=>'Leisure : Movie Theaters','description'=>'Location for displaying long-format content on large screens.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>80201,'string_value'=>'entertainment.movie_theaters.lobby','category'=>'Leisure','subcategory'=>'Movie Theaters','venue_type'=>'Leisure : Movie Theaters : Lobby','description'=>'A corridor or hall used as a passageway or waiting room','depth'=>2,'parent_enum_id'=>802],
            ['enumeration_id'=>80202,'string_value'=>'entertainment.movie_theaters.food_court','category'=>'Leisure','subcategory'=>'Movie Theaters','venue_type'=>'Leisure : Movie Theaters : Food Court','description'=>'An area within a building set apart for food concessions.','depth'=>2,'parent_enum_id'=>802],
            ['enumeration_id'=>803,'string_value'=>'entertainment.sports','category'=>'Leisure','subcategory'=>'Sports Entertainment','venue_type'=>'Leisure : Sports Entertainment','description'=>'A venue where individuals or groups can play an active sport or activity.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>80301,'string_value'=>'entertainment.sports.arena','category'=>'Leisure','subcategory'=>'Sports Entertainment','venue_type'=>'Leisure : Sports Entertainment : Sport Arena','description'=>'A central area used for sports or entertainment, surrounded by seats for spectators.','depth'=>2,'parent_enum_id'=>803],
            ['enumeration_id'=>80302,'string_value'=>'entertainment.sports.club_house','category'=>'Leisure','subcategory'=>'Sports Entertainment','venue_type'=>'Leisure : Sports Entertainment : Club House','description'=>'Locker rooms used by an athletic team','depth'=>2,'parent_enum_id'=>803],
            ['enumeration_id'=>804,'string_value'=>'entertainment.bars','category'=>'Leisure','subcategory'=>'Bars','venue_type'=>'Leisure : Bars','description'=>'A retail business that serves alcoholic beverages.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>805,'string_value'=>'entertainment.casual_dining','category'=>'Leisure','subcategory'=>'Casual Dining','venue_type'=>'Leisure : Casual Dining','description'=>'A restaurant that serves moderately priced food in a casual atmosphere.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>806,'string_value'=>'entertainment.qsr','category'=>'Leisure','subcategory'=>'QSR','venue_type'=>'Leisure : QSR','description'=>'A fast food / quick service restaurant.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>807,'string_value'=>'entertainment.hotels','category'=>'Leisure','subcategory'=>'Hotels','venue_type'=>'Leisure : Hotels','description'=>'An establishment providing accommodations and services for travelers.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>80701,'string_value'=>'entertainment.hotels.lobby','category'=>'Leisure','subcategory'=>'Hotels','venue_type'=>'Leisure : Hotels : Lobby','description'=>'Commonly accessible shared spaces for guests at a hotel','depth'=>2,'parent_enum_id'=>807],
            ['enumeration_id'=>80702,'string_value'=>'entertainment.hotels.elevator','category'=>'Leisure','subcategory'=>'Hotels','venue_type'=>'Leisure : Hotels : Elevator','description'=>'Enclosed spaces used to move between floors.','depth'=>2,'parent_enum_id'=>807],
            ['enumeration_id'=>80703,'string_value'=>'entertainment.hotels.room','category'=>'Leisure','subcategory'=>'Hotels','venue_type'=>'Leisure : Hotels : Room','description'=>'Locations occupied and restricted to a single guest','depth'=>2,'parent_enum_id'=>807],
            ['enumeration_id'=>808,'string_value'=>'entertainment.golf_cart','category'=>'Leisure','subcategory'=>'Golf Carts','venue_type'=>'Leisure : Golf Carts','description'=>'A small motorized vehicle for golfers and their equipment.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>809,'string_value'=>'entertainment.night_club','category'=>'Leisure','subcategory'=>'Night Clubs','venue_type'=>'Leisure : Night Clubs','description'=>'An establishment for nighttime entertainment, typically serving drinks and offering music.','depth'=>1,'parent_enum_id'=>8],
            ['enumeration_id'=>810,'string_value'=>'entertainment.high_end_dining','category'=>'Leisure','subcategory'=>'High-End Dining','venue_type'=>'Leisure : High-End Dining','description'=>'A restaurant that serves expensive food in a formal atmosphere.','depth'=>1,'parent_enum_id'=>8],

            // ── Government ────────────────────────────────────────────────────
            ['enumeration_id'=>9,'string_value'=>'government','category'=>'Government','subcategory'=>'','venue_type'=>'Government','description'=>'Government venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>901,'string_value'=>'government.dmv','category'=>'Government','subcategory'=>'DMVs','venue_type'=>'Government : DMVs','description'=>'Department of Motor Vehicles offices.','depth'=>1,'parent_enum_id'=>9],
            ['enumeration_id'=>902,'string_value'=>'government.military_bases','category'=>'Government','subcategory'=>'Military Bases','venue_type'=>'Government : Military Bases','description'=>'A facility that houses and facilitates training for military personnel.','depth'=>1,'parent_enum_id'=>9],
            ['enumeration_id'=>903,'string_value'=>'government.postal','category'=>'Government','subcategory'=>'Post Offices','venue_type'=>'Government : Post Offices','description'=>'A facility that handles the receipt, delivery, and processing of mail.','depth'=>1,'parent_enum_id'=>9],

            // ── Financial ─────────────────────────────────────────────────────
            ['enumeration_id'=>10,'string_value'=>'financial','category'=>'Financial','subcategory'=>'','venue_type'=>'Financial','description'=>'Financial venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>1001,'string_value'=>'financial.banks','category'=>'Financial','subcategory'=>'Banks','venue_type'=>'Financial : Banks','description'=>'A financial institution licensed to store or invest accountholders money.','depth'=>1,'parent_enum_id'=>10],

            // ── Residential ───────────────────────────────────────────────────
            ['enumeration_id'=>11,'string_value'=>'residential','category'=>'Residential','subcategory'=>'','venue_type'=>'Residential','description'=>'Residential venues','depth'=>0,'parent_enum_id'=>null],
            ['enumeration_id'=>1101,'string_value'=>'residential.apartment','category'=>'Residential','subcategory'=>'Apartment Buildings and Condominiums','venue_type'=>'Residential : Apartment Buildings and Condominiums','description'=>'A building that contains different residential units.','depth'=>1,'parent_enum_id'=>11],
            ['enumeration_id'=>110101,'string_value'=>'residential.apartment_buildings.lobby','category'=>'Residential','subcategory'=>'Apartment Buildings and Condominiums','venue_type'=>'Residential : Apartment Buildings and Condominiums : Lobby','description'=>'A corridor or hall used as a passageway or waiting room in an apartment building','depth'=>2,'parent_enum_id'=>1101],
            ['enumeration_id'=>110102,'string_value'=>'residential.apartment_buildings.elevator','category'=>'Residential','subcategory'=>'Apartment Buildings and Condominiums','venue_type'=>'Residential : Apartment Buildings and Condominiums : Elevator','description'=>'Enclosed vertical conveyance for people and goods in an apartment building','depth'=>2,'parent_enum_id'=>1101],
        ];
    }
}
