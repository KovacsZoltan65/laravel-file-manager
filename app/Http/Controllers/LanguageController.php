<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * Állítsa be az alkalmazás területi beállítását a kérés bemenete vagy a
     * alapértelmezett területi beállítás. Átirányítás az előző oldalra.
     *
     * @param Request $request A HTTP kérés objektuma.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        // Szerezze be a területi beállítást a kérés bemenetéből, 
        //vagy használja az alapértelmezett területi beállítást
        $locale = $request->input('locale', config('app.locale'));
        
        // Ellenőrizze, hogy a nyelv támogatott-e
        if( $this->isLocaleSupported($locale) ) {
            // Állítsa be az alkalmazás területi beállítását
            app()->setLocale($locale);
            
            // Tárolja a nyelvi beállítást a munkamenetben
            $request->session()->put('locale', $locale);
        }
        
        // Átirányítás az előző oldalra
        return redirect()->back();
    }
    
    /**
     * Ellenőrizze, hogy az adott területet támogatja-e az alkalmazás.
     *
     * @param string $locale Az ellenőrizendő terület.
     * @return bool Igaz, ha a területi beállítás támogatott, hamis egyébként.
     */
    private function isLocaleSupported($locale)
    {
        // Szerezze meg a támogatott területek listáját
        $supportedLocales = config('app.supported_locales');
        
        // Ellenőrizze, hogy a nyelvi beállítás szerepel-e a támogatott nyelvi beállítások listáján
        return in_array($locale, $supportedLocales);
    }
}
