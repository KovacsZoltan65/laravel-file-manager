<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToFavouritesRequest;
use App\Http\Requests\FilesActionRequest;
use App\Http\Requests\ShareFilesRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\TrashFilesRequest;
use App\Http\Resources\FileResource;
use App\Jobs\UploadFileToCloudJob;
use App\Mail\ShareFilesMail;
use App\Models\File;
use App\Models\FileShare;
use App\Models\StarredFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FileController extends Controller
{
    /**
     * Jelenítse meg a hitelesített felhasználó fájljait.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $folder
     * @return \Inertia\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function myFiles(Request $request, string $folder = null)
    {
        // A keresési paraméter lekérése a kérelemből
        $search = $request->get('search');

        /**
         * Kérje le a mappát a megadott elérési úttal, ha létezik
         *
         * @param string|null $folder A visszakeresendő mappa elérési útja
         * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a mappa nem létezik
         * @return \App\Models\File A letöltött mappa
         */
        if ($folder) {
            // Keresse le a mappát a megadott elérési úttal
            // A mappának a hitelesített felhasználóhoz kell tartoznia, és az elérési útnak meg kell egyeznie a megadott elérési úttal
            $folder = File::query()
                ->where('created_by', Auth::id())
                ->where('path', $folder)
                ->firstOrFail();
        }

        /**
         * Szerezze be a gyökérmappát, ha nincs megadva mappa.
         *
         * @return \App\Models\File A gyökérmappa
         */
        if (!$folder) {
            // Szerezd meg a gyökérmappát
            $folder = $this->getRoot();
        }

        // Szerezze be a 'favorites' paramétert a kérésből
        $favourites = (int)$request->get('favourites');

        // Készítse el a lekérdezést a fájlok lekéréséhez
        $query = File::query()
            ->select('files.*')
            ->with('starred')
            ->where('created_by', Auth::id())
            ->where('_lft', '!=', 1)  // Zárja ki a gyökérmappát
            ->orderBy('is_folder', 'desc')  // Rendezze a mappákat a fájlok előtt
            ->orderBy('files.created_at', 'desc')  // Rendezés a létrehozás dátuma szerint
            ->orderBy('files.id', 'desc');  // Rendelés azonosító szerint

        // Ha van megadva keresési feltétel, szűrje az eredményeket név szerint
        if ($search) {
            $query->where('name', 'like', "%$search%");
        } else {
            // Ha nincs megadva keresési érték, szűrje az eredményeket szülőazonosító szerint
            $query->where('parent_id', $folder->id);
        }

        // Ha a kedvencek beállítása 1, csatlakozzon a starred_files táblához, és szűrjön a hitelesített felhasználó szerint
        if ($favourites === 1) {
            $query->join('starred_files', 'starred_files.file_id', '=', 'files.id')
                ->where('starred_files.user_id', Auth::id());
        }

        // Lapozás beáálítása
        $result = $query->paginate(10);

        // Alakítsa át a fájlokat FileResource objektumok gyűjteményévé
        $files = FileResource::collection($result);

        // Ha a kérelem JSON-választ szeretne, küldje vissza a fájlokat
        if ($request->wantsJson()) {
            return $files;
        }

        // Építsd fel az ősök gyűjteményét
        $ancestors = FileResource::collection([...$folder->ancestors, $folder]);

        // Hozzon létre egy új FileResource objektumot a mappához
        $folder = new FileResource($folder);

        // Jelenítse meg a MyFiles nézetet a fájlokkal, mappákkal és elődökkel
        return Inertia::render('MyFiles', compact('files', 'folder', 'ancestors'));
    }

    /**
     * Jelenítse meg a kukában lévő fájlok listáját.
     *
     * @param Request $request A HTTP kérés objektuma.
     * @return \Inertia\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection A megjelenített oldal vagy JSON-válasz.
     */
    public function trash(Request $request)
    {
        // Szerezze le a keresési értéket a kérelemből, és inicializálja a lekérdezéskészítőt
        $search = $request->get('search');
        $query = File::onlyTrashed()
            ->where('created_by', Auth::id())  // Szűrés a hitelesített felhasználó szerint
            ->orderBy('is_folder', 'desc')  // Először rendezés mappák szerint
            ->orderBy('deleted_at', 'desc')  // Rendelés törlési dátum szerint
            ->orderBy('files.id', 'desc');  // Rendelés fájlazonosító szerint

        // Ha van keresési érték, szűrje a fájlokat név szerint
        if ($search) {
            $query->where('name', 'like', "%$search%");
        }

        // Lapozzás beállítása
        $files = $query->paginate(10);

        // Alakítsa át a fájlokat FileResource objektummá
        $files = FileResource::collection($files);

        // Ha a kérelem JSON-választ szeretne, adja vissza a fájlokat JSON-ként
        if ($request->wantsJson()) {
            return $files;
        }

        // Jelenítse meg a Kuka oldalt a fájlokkal
        return Inertia::render('Trash', compact('files'));
    }

    /**
     * Jelenítse meg a megosztott fájlok listáját, amit a felhasználó kapott.
     *
     * @param Request $request A HTTP kérés objektuma.
     * @return \Inertia\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection A megjelenített oldal vagy JSON-válasz.
     */
    public function sharedWithMe(Request $request)
    {
        // Szerezze le a keresési értéket a kérelemből, és inicializálja a lekérdezéskészítőt
        $search = $request->get('search');
        $query = File::getSharedWithMe();

        // Ha van keresési érték, szűrje a fájlokat név szerint
        if ($search) {
            $query->where('name', 'like', "%$search%");
        }

        // Lapozzás beállítása
        $result = $query->paginate(10);

        // Alakítsa át a fájlokat FileResource objektummá
        $files = FileResource::collection($result);

        // Ha a kérelem JSON-választ szeretne, adja vissza a fájlokat JSON-ként
        if ($request->wantsJson()) {
            return $files;
        }

        // Jelenítse meg a Megosztottak oldalt a fájlokkal
        return Inertia::render('SharedWithMe', compact('files'));
    }

    /**
     * Jelenítse meg a megosztott fájlok listáját, amit a felhasználó elküldött.
     *
     * @param Request $request A HTTP kérés objektuma.
     * @return \Inertia\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection A megjelenített oldal vagy JSON-válasz.
     */
    public function sharedByMe(Request $request)
    {
        // Szerezze le a keresési értéket a kérelemből, és inicializálja a lekérdezéskészítőt
        $search = $request->get('search');
        $query = File::getSharedByMe();

        // Ha van keresési érték, szűrje a fájlokat név szerint
        if ($search) {
            $query->where('name', 'like', "%$search%");
        }

        // Lapozzás beállítása
        $result = $query->paginate(10);

        // Alakítsa át a fájlokat FileResource objektummá
        $files = FileResource::collection($result);

        // Ha a kérelem JSON-választ szeretne, adja vissza a fájlokat JSON-ként
        if ($request->wantsJson()) {
            return $files;
        }

        // Jelenítse meg a Megosztottak oldalt a fájlokkal
        return Inertia::render('SharedByMe', compact('files'));
    }

    /**
     * Hozzon létre egy új mappát.
     *
     * @param StoreFolderRequest $request A HTTP kérés objektuma.
     * @return void
     */
    public function createFolder(StoreFolderRequest $request)
    {
        // Szerezze be az érvényesített adatokat a kérésből
        $data = $request->validated();

        // Szerezze be a szülőmappát a kérésből, vagy használja a gyökérmappát, ha nincs megadva
        $parent = $request->parent ?? $this->getRoot();

        // Hozzon létre egy új mappát
        $file = new File();
        $file->is_folder = 1;
        $file->name = $data['name'];

        // Csatlakoztassa az új mappát a szülőmappához
        $parent->appendNode($file);
    }

    /**
     * Mentse a fájlokat a rendszeren.
     *
     * @param StoreFileRequest $request A HTTP kérés objektuma, amely tartalmazza a fájlokat
     * @return void
     */
    public function store(StoreFileRequest $request)
    {
        // Szerezze be az érvényesített adatokat a kérésből
        $data = $request->validated();

        // Szerezze be a szülőmappát a kérésből, vagy használja a gyökérmappát, ha nincs megadva
        $parent = $request->parent;
        if (!$parent) {
            $parent = $this->getRoot();
        }

        // Ellenőrizze, hogy van-e fájltree a kérésben
        $fileTree = $request->file_tree;
        if (!empty($fileTree)) {
            // Ha van, mentse a fájltree-t a rendszeren
            $this->saveFileTree($fileTree, $parent, $request->user());
        } else {
            // Ellenőrizze, hogy van-e fájl a kérésben
            foreach ($data['files'] as $file) {
                /** @var \Illuminate\Http\UploadedFile $file */

                // Mentse a fájlt a rendszeren
                $this->saveFile($file, $request->user(), $parent);
            }
        }
    }

    /**
     * Szerezze be a hitelesített felhasználó gyökérmappáját.
     *
     * @return \App\Models\File A gyökérmappa
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a gyökérmappa nem található
     */
    private function getRoot()
    {
        // Kérje le a hitelesített felhasználó gyökérmappáját
        // A mappát gyökérmappaként kell megjelölni, és a hitelesített felhasználóhoz kell tartoznia
        return File::query()
            ->whereIsRoot()  // Keresse meg a gyökérmappát
            ->where('created_by', Auth::id())  // A mappának a hitelesített felhasználóhoz kell tartoznia
            ->firstOrFail();  // Adjon kivételt, ha a gyökérmappa nem található
    }

    /**
     * Mentse a fájltree-t a rendszeren.
     *
     * @param array $fileTree A fájltree, amelyet menteni kell
     * @param \App\Models\File $parent A szülőmappa, amelyhez a fájltree-t hozzá kell rendelni
     * @param \App\Models\User $user A felhasználó, akit a fájlokhoz rendelni kell
     * @return void
     */
    public function saveFileTree(array $fileTree, File $parent, User $user)
    {
        // Mentse minden fájlt és mappát a fájltree-ből
        foreach ($fileTree as $name => $file) {
            // Ha a fájl egy tömb, akkor ez egy mappa, és meg kell hívni a függvényt az almappán
            if (is_array($file)) {
                $folder = new File();
                $folder->is_folder = 1;
                $folder->name = $name;

                // Csatolja a mappát a szülőmappához
                $parent->appendNode($folder);

                // Mentse a fájltree-t az almappában
                $this->saveFileTree($file, $folder, $user);
            } else {
                // Ellenőrizze, hogy a fájl egy UploadedFile-típusú objektum, és mentse a fájlt a rendszeren
                $this->saveFile($file, $user, $parent);
            }
        }
    }

    /**
     * Törli a kiválasztott fájlokat vagy a mappa összes fájlját.
     *
     * @param FilesActionRequest $request A HTTP kérés objektuma, amely tartalmazza a fájlokat és az átadott mappát
     * @return \Illuminate\Http\RedirectResponse A felhasználó fájljai lapja a törölt mappában
     */
    public function destroy(FilesActionRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;

        // Ha minden fájlt törölni kell, akkor törölje az összes gyermekfájlt
        if ($data['all']) {
            $children = $parent->children;

            foreach ($children as $child) {
                $child->moveToTrash();  // Mentse a fájlt a kukába
            }
        } else {
            // Ellenőrizze, hogy a fájl létezik, és törölje a fájlt a rendszeren
            foreach ($data['ids'] ?? [] as $id) {
                $file = File::find($id);
                if ($file) {
                    $file->moveToTrash();  // Mentse a fájlt a kukába
                }
            }
        }

        return to_route('myFiles', ['folder' => $parent->path]);  // Visszatérés a fájlok oldalára a törölt mappában
    }

    public function download(FilesActionRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }

        if ($all) {
            $url = $this->createZip($parent->children);
            $filename = $parent->name . '.zip';
        } else {
            [$url, $filename] = $this->getDownloadUrl($ids, $parent->name);
        }

        return [
            'url' => $url,
            'filename' => $filename
        ];
    }

    /**
     *
     *
     * @param $file
     * @param $user
     * @param $parent
     * @author Zura Sekhniashvili <zurasekhniashvili@gmail.com>
     */
    private function saveFile($file, $user, $parent): void
    {
        $path = $file->store('/files/' . $user->id, 'local');

        $model = new File();
        $model->storage_path = $path;
        $model->is_folder = false;
        $model->name = $file->getClientOriginalName();
        $model->mime = $file->getMimeType();
        $model->size = $file->getSize();
        $model->uploaded_on_cloud = 0;

        $parent->appendNode($model);

        UploadFileToCloudJob::dispatch($model);
    }

    public function createZip($files): string
    {
        $zipPath = 'zip/' . Str::random() . '.zip';
        $publicPath = "$zipPath";

        if (!is_dir(dirname($publicPath))) {
            Storage::disk('public')->makeDirectory(dirname($publicPath));
        }

        $zipFile = Storage::disk('public')->path($publicPath);

        $zip = new \ZipArchive();

        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $this->addFilesToZip($zip, $files);
        }

        $zip->close();

        return asset(Storage::disk('local')->url($zipPath));
    }

    private function addFilesToZip($zip, $files, $ancestors = '')
    {
        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->addFilesToZip($zip, $file->children, $ancestors . $file->name . '/');
            } else {
                $localPath = Storage::disk('local')->path($file->storage_path);
                if ($file->uploaded_on_cloud == 1) {
                    $dest = pathinfo($file->storage_path, PATHINFO_BASENAME);
                    $content = Storage::get($file->storage_path);
                    Storage::disk('public')->put($dest, $content);
                    $localPath = Storage::disk('public')->path($dest);
                }

                $zip->addFile($localPath, $ancestors . $file->name);
            }
        }
    }

    public function restore(TrashFilesRequest $request)
    {
        $data = $request->validated();
        if ($data['all']) {
            $children = File::onlyTrashed()->get();
            foreach ($children as $child) {
                $child->restore();
            }
        } else {
            $ids = $data['ids'] ?? [];
            $children = File::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($children as $child) {
                $child->restore();
            }
        }

        return to_route('trash');
    }

    public function deleteForever(TrashFilesRequest $request)
    {
        $data = $request->validated();
        if ($data['all']) {
            $children = File::onlyTrashed()->get();
            foreach ($children as $child) {
                $child->deleteForever();
            }
        } else {
            $ids = $data['ids'] ?? [];
            $children = File::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($children as $child) {
                $child->deleteForever();
            }
        }

        return to_route('trash');
    }

    public function addToFavourites(AddToFavouritesRequest $request)
    {
        $data = $request->validated();

        $id = $data['id'];
        $file = File::find($id);
        $user_id = Auth::id();

        $starredFile = StarredFile::query()
            ->where('file_id', $file->id)
            ->where('user_id', $user_id)
            ->first();

        if ($starredFile) {
            $starredFile->delete();
        } else {
            StarredFile::create([
                'file_id' => $file->id,
                'user_id' => $user_id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return redirect()->back();
    }

    public function share(ShareFilesRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;

        $all = $data['all'] ?? false;
        $email = $data['email'] ?? false;
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to share'
            ];
        }

        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            return redirect()->back();
        }

        if ($all) {
            $files = $parent->children;
        } else {
            $files = File::find($ids);
        }

        $data = [];
        $ids = Arr::pluck($files, 'id');
        $existingFileIds = FileShare::query()
            ->whereIn('file_id', $ids)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('file_id');

        foreach ($files as $file) {
            if ($existingFileIds->has($file->id)) {
                continue;
            }
            $data[] = [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
        FileShare::insert($data);

        Mail::to($user)->send(new ShareFilesMail($user, Auth::user(), $files));

        return redirect()->back();
    }

    public function downloadSharedWithMe(FilesActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }

        $zipName = 'shared_with_me';
        if ($all) {
            $files = File::getSharedWithMe()->get();
            $url = $this->createZip($files);
            $filename = $zipName . '.zip';
        } else {
            [$url, $filename] = $this->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'filename' => $filename
        ];
    }

    public function downloadSharedByMe(FilesActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }

        $zipName = 'shared_by_me';
        if ($all) {
            $files = File::getSharedByMe()->get();
            $url = $this->createZip($files);
            $filename = $zipName . '.zip';
        } else {
            [$url, $filename] = $this->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'filename' => $filename
        ];
    }

    private function getDownloadUrl(array $ids, $zipName)
    {
        if (count($ids) === 1) {
            $file = File::find($ids[0]);
            if ($file->is_folder) {
                if ($file->children->count() === 0) {
                    return [
                        'message' => 'The folder is empty'
                    ];
                }
                $url = $this->createZip($file->children);
                $filename = $file->name . '.zip';
            } else {
                $dest = pathinfo($file->storage_path, PATHINFO_BASENAME);
                if ($file->uploaded_on_cloud) {
                    $content = Storage::get($file->storage_path);
                } else {
                    $content = Storage::disk('local')->get($file->storage_path);
                }

                Log::debug("Getting file content. File:  " .$file->storage_path).". Content: " .  intval($content);

                $success = Storage::disk('public')->put($dest, $content);
                Log::debug('Inserted in public disk. "' . $dest . '". Success: ' . intval($success));
                $url = asset(Storage::disk('public')->url($dest));
                Log::debug("Logging URL " . $url);
                $filename = $file->name;
            }
        } else {
            $files = File::query()->whereIn('id', $ids)->get();
            $url = $this->createZip($files);

            $filename = $zipName . '.zip';
        }

        return [$url, $filename];
    }
}
