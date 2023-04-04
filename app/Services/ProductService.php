<?php
namespace App\Services;
use Illuminate\Database\Eloquent\Builder as eloBuilder;
use App\Libraries\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Traits\{
    StaticResponseTrait,
    UploadTrait,
    DbTrait
};
use App\Models\{
    Product,
    ProductImage
};
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Statics\ProductTypeStatic;
use Illuminate\Database\Eloquent\Model;
use stdClass;


use function PHPUnit\Framework\isEmpty;

class ProductService {

    use StaticResponseTrait,UploadTrait,DbTrait;

    public function create(Request $request){
        $productTypes = [ProductTypeStatic::$SINGLE, ProductTypeStatic::$GROUP];
        $validated = Validator::make($request->all(), [
            'type' => 'required|numeric|in:'.implode(',',$productTypes),
            'code' => 'required|string|unique:products',
            'barcode' => 'required|string|unique:products',
            'sku' => 'required|string|unique:products',
            'name' => 'required|string',
            'unit_id' => 'required|numeric',
            'height' => 'numeric',
            'weight' => 'numeric',
            'width' => 'numeric',
            'description' => 'string',
        ],[
            'required' => ':attribute cannot be null',
            'string' => ':attribute must be a string',
            'numeric' => ':attribute must be a numeric',
            'unique' => 'the :attribute must be unique',
            'type.in' => 'type must be in ('. implode(',',$productTypes).')'
        ]);

        if ($validated->fails()) {
            return $this->response400($validated->errors()->first());
        }
        
        try {
            DB::beginTransaction();

            $product = new Product;
            $product->code = trim($request->code);
            $product->sku = trim($request->sku);
            $product->type = (int) $request->type;
            $product->name = trim($request->name);
            $product->unit_id = (int) $request->unit_id;
            $product->description = $request->description;
            $product->barcode = trim($request->barcode);
            if(!$product->save()){
                return $this->response400('Cannot save product');
            }

            /**
             * Proses Image
             */
            $resultImages = [];
            $images = $request->file();
           
            foreach ($images as $key => $image) {
                $iKey = `image_`.$key++;
                $options = [
                    'file' => $iKey,
                    'size' => [500,300],
                    'path' => 'uploads/products/images'
                    
                ];
                $resultImage = $this->uploadImage($request,$options);
                if(empty($resultImage)) {
                    DB::rollBack();
                    return $this->response400('Cannot upload image');
                }
                array_push($resultImages, $resultImage);
            }

            foreach ($resultImages as $key => $img) {
                $success = ProductImage::create([
                    'url' => $img['path'],
                    'product_id' => $product->id
                ]);

                if(!$success) {
                    DB::rollBack();
                    return $this->response400('Cannot save product, Product Image not valid');
                }
            }
        
            DB::commit();
            return ApiResponse::make(true,'Data Inserted',$product);
            
        } catch (\Throwable $th) {
            return $this->response500($th);
        }


    }

    public function edit(Request $request){
        $productTypes = [ProductTypeStatic::$SINGLE, ProductTypeStatic::$GROUP];
        $validated = Validator::make($request->all(), [
            'type' => 'required|numeric|in:'.implode(',',$productTypes),
            'code' => 'required|string',
            'barcode' => 'required|string',
            'sku' => 'required|string',
            'name' => 'required|string',
            'unit_id' => 'required|numeric',
            'height' => 'numeric',
            'weight' => 'numeric',
            'width' => 'numeric',
            'description' => 'string',
        ],[
            'required' => ':attribute cannot be null',
            'string' => ':attribute must be a string',
            'numeric' => ':attribute must be a numeric',
            'unique' => 'the :attribute must be unique',
            'type.in' => 'type must be in ('. implode(',',$productTypes).')'
        ]);

        if ($validated->fails()) {
            return $this->response400($validated->errors()->first());
        }
        
        try {
            DB::beginTransaction();

            $product = new Product;
            /* $product = new Product;
            $product->code = trim($request->code);
            $product->sku = trim($request->sku);
            $product->type = (int) $request->type;
            $product->name = trim($request->name);
            $product->unit_id = (int) $request->unit_id;
            $product->description = $request->description;
            $product->barcode = trim($request->barcode); */
            $product = Product::where('id',$request->id)->first();
            if (empty($product)) {
                return $this->response400('Product Not Exist !');
            }
            
            if($product->code!=$request->code){
                $cek = Product::where('code',$request->code)->first();
                if (!empty($cek)) {
                    return $this->response400('Code has been used !');
                }
            }

            $update = [
                'code' => trim($request->code),
                'sku' => trim($request->sku),
                'type' => (int)$request->type,
                'name' => trim($request->name),
                'unit_id' => (int)$request->unit_id,
                'description' => $request->description,
                'barcode' => $request->barcode
            ];
            //dd($request->toArray());
            //$product = new Product;
            $product->code = trim($request->code);
            $product->sku = trim($request->sku);
            $product->type = (int) $request->type;
            $product->name = trim($request->name);
            $product->unit_id = (int) $request->unit_id;
            $product->description = $request->description;
            $product->barcode = trim($request->barcode);
            
            if(!$product->save()){
                return $this->response400('Cannot Update product !');
            }

            /**
             * Proses Image
             */
         $resultImages = [];
            $images = $request->file();
           
            foreach ($images as $key => $image) {
                $iKey = `image_`.$key++;
                $options = [
                    'file' => $iKey,
                    'size' => [500,300],
                    'path' => 'uploads/products/images',
                    'permission' => 777
                ];
                $resultImage = $this->uploadImage($request,$options);
                if(empty($resultImage)) {
                    DB::rollBack();
                    return $this->response400('Cannot upload image');
                }
                array_push($resultImages, $resultImage);
            }
            
            $delete_image = ProductImage::where('product_id',$product->id)->get();
            if ($delete_image->count()>0) {
                if(!ProductImage::where('product_id',$product->id)->delete()){
                    DB::rollBack();
                    return $this->response400('Cannot Update Product Image');
                }
            }
            
            foreach ($resultImages as $key => $img) {
                $success = ProductImage::create([
                    'url' => $img['path'],
                    'product_id' => $product->id
                ]);

                if(!$success) {
                    DB::rollBack();
                    return $this->response400('Cannot save product, Product Image not valid');
                }
            }
       
            DB::commit();
            return ApiResponse::make(true,'Data Updated',$update);
            
        } catch (\Throwable $th) {
            return $this->response500($th);
        }


    }

    public function detail(Request $request,$id){
        $products = Product::with('unit')->where('id',$id)->get();
        return ApiResponse::make(true, 'BERHASIL LOAD  DATA',$products);
    }
    
    public function hapus(Request $request,$id){
        $products = Product::with('unit')->where('id',$id)->delete();
        return ApiResponse::make(true, 'BERHASIL HAPUS DATA',$products); 
    }

    public function list(Request $request){
        $preload = Product::with('unit','images');

        $total = Product::get()->count();
        $this->queryFilters($preload);
        $this->limiter($preload); 
       // dd($preload->toSql());
        $products = $preload->get();
        ApiResponse::setIncludeData(['jumlah'=>$products->count(),'total'=>$total]);
        return ApiResponse::make(true, 'BERHASIL LOAD '.count($products). ' DATA',$products);
    }

    private function queryFilters(eloBuilder &$model){
        $filters = [];
        $requests = request()->all(); 
        foreach($requests as $key => $value){
            $_filter = explode("_",$key);
            if (count($_filter)<=1) continue;
           // dd($_filter);
            switch ($_filter[count($_filter)-1]) {
                case 'contain':
                    /* dd($_filter);
                    array_push($filters,[$_filter[0]=>$_filter[1]]);*/
                    unset($_filter[count($_filter)-1]);
                    $column= implode("_",$_filter);
                    //dd($column);
                    $model->where($column,"LIKE","%$value%");
                    break;
                
                default:
                    # code...
                    break;
            }
        }
       // dd($model->toSql());
       return $model;

    }

}