@if(isset($listings[0]))
    <div class="listOfListings @if($rel) listOfListings_no_follow @endif">
        {{--<h5 class="uppercase">--}}
            {{--{{$title}}--}}
        {{--</h5>--}}
        @foreach($listings as $listing)
            <div class="post-snippet mb64">
                @if(isset($tpl_structure['rows']))
                    <a href="{{Tr::getSLang()}}/{!! $listing_type->{Tr::go('url')} !!}/{!! $listing->{Tr::go('url')} !!}/">
                        @foreach($tpl_structure['rows'] as $k_row => $v_row)
                            <div class="col-md-4">
                                @if(isset($v_row['colls']))
                                    @foreach($v_row['colls'] as $k_col => $v_col)
                                        <div class="col-md-{{$v_col['width']}}">
                                            @if(isset($v_col['metrics']))
                                                @foreach($v_col['metrics'] as $k_metric => $v_metric)
                                                    @if(isset($modules_data[$listing->id][$v_metric]))
                                                        @foreach($modules_data[$listing->id][$v_metric] as $mod)
                                                            {!!Utils::getModule($mod['module_type'])->setListing($listing)->setFieldData($mod)->getFrontendHtml()!!}
                                                        @endforeach
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    </a>
                @endif
            </div>
        @endforeach
    </div>
    <a href="{{Tr::getSLang()}}/{!!$listing_type->{Tr::go('url')} !!}" class="btn"><?= trans('translate.list_of_listings_btn_see_all')?></a>
@endif