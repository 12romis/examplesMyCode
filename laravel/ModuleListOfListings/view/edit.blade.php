<div class="row connected" @if($settings['criteria']!='latest') style="display: none" @endif>
    <div class="col-md-12">
        <div>
            <h4>
                All connected listings:
                <span class="badge bg-success popovers" data-original-title="Info" data-content="If you need quickly edit connected listing click on him." data-placement="top" data-trigger="hover">
                <i class="icon-info"></i>
            </span>
            </h4>
            @foreach($connected as $key=>$listing)
                <a href="/admin/listings/{{$listing->listing_type_id}}/edit/{{$listing->id}}" target="_blank">
                    {{$listing->name}}@if ($key!=$connected->count()-1){{', '}}@endif
                </a>
            @endforeach
        </div>
        <br>
    </div>
</div>