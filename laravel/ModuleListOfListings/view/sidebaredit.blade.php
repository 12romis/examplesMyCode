<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Count per page:</label>
            <input class="form-control" type="text" name="fielddata[][settings][count]"
                   value="@if($settings['count'] == ''){{5}}@else{{$settings['count']}}@endif"/>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Listing type:</label>
            <select class="form-control" name="fielddata[][settings][listingType]">
                @foreach($listing_types as $item)
                    <option value="{{$item->id}}" @if($item->id == $settings['listingType']) selected @endif>{{$item->name}}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="form-group">
    <label for="timeDuration">Rel no follow?</label>
    <select class="form-control" name="fielddata[][settings][rel]">
        <option value="1" @if($settings['rel']==1) selected @endif>yes</option>
        <option value="0" @if($settings['rel']==0) selected @endif>no</option>
    </select>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Choose criteria:</label>
            <select class="form-control criteriaMostPopular" name="fielddata[][settings][criteria]">
                <option value="ratings" @if($settings['criteria']=='ratings') selected @endif>ratings</option>
                <option value="comments" @if($settings['criteria']=='comments') selected @endif>comments</option>
                <option value="latest" @if($settings['criteria']=='latest') selected @endif>latest news</option>
                <option value="similar" @if($settings['criteria']=='similar') selected @endif>similar</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group timeDuration" @if($settings['criteria']!='comments') style="display: none" @endif>
            <label>Time duration:</label>
            <select class="form-control" name="fielddata[][settings][timeDuration]">
                <option value="1" @if($settings['timeDuration']==1) selected @endif>day</option>
                <option value="7" @if($settings['timeDuration']==7) selected @endif>week</option>
                <option value="30" @if($settings['timeDuration']==30) selected @endif>month</option>
                <option value="365" @if($settings['timeDuration']==365) selected @endif>year</option>
            </select>
        </div>
    </div>
</div>