"use strict";

import $ from "jquery";
import validate from "jquery-validation";
import mask from "jquery-mask-plugin";
import selectify from "selectify";
import Menu from "../controllers/menu";
import Confirm from "../controllers/confirm";
import Status from "../controllers/statusPanel";
import PhotoPreview from "../controllers/photoPreview";

window.$ = $;

$(function(){

	new Menu();
	new Status();
	new Confirm();
	new PhotoPreview();

	$(".select-holder").selectify();
	$("form").validate();
});