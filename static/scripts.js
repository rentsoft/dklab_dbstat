var GRAPH_WIDTH = 0, GRAPH_HEIGHT = 300, GRAPH_PADDING = 10, GRAPH_BORDER = 2;
var GRAPH_COLORS = [ [ '#F00', '#FBB' ], [ '#0A0', '#9F9' ] ];


function getValuesOfRows(trs) {
	var POS_DATA = 4;

	var setOfTdsList = [];
	var vAxes = [];
	var series = [];
	$.each(trs, function(i) {
		setOfTdsList.push($(this).children('td').slice(POS_DATA));
		var color = GRAPH_COLORS[i][0];
		var gridColor = GRAPH_COLORS[i][1];
		vAxes.push({
			gridlineColor: gridColor,
			textStyle: { color: color },
			viewWindowMode: 'maximized'
		});
		series.push({
			targetAxisIndex: i,
			color: color,
			lineWidth: 4 + (trs.length - i) * 2
		});
	});
	
	var data = [];
	var headTds = $($(trs[0]).closest('table')[0].firstChild.firstChild).find('td').slice(POS_DATA);
	$.each(headTds, function(i) {
		var $headTd = $(this);
		var col = [];
		col.push($headTd.html().replace(/<.*?>/g, ' '));
		$.each(setOfTdsList, function() {
			var $td = $(this[i]);
			var value = ($td.attr("value")||"");
			if (!value.length || $td.hasClass('incomplete')) value = null;
			else value = parseFloat(value);
			col.push(value);
		});
		data.push(col);
	})
	
	// Remove first columns if they have at least one incomplete value -
	// this is needed, because we should not draw zero-point for incomplete data.
	while (data.length) {
		if ($.grep(data[0], function(e) { return e == null }).length) {
			data = data.slice(1);
		} else {
			break;
		}
	}
	
	return {
		data: data,
		vAxes: vAxes,
		series: series
	};
}


function showGraph(trs, x, xl, yTop, yBottom, w, h) {
	var values = getValuesOfRows(trs);
	if (!values.data.length) return;

	var gap = GRAPH_PADDING + GRAPH_BORDER;
	if (!w) {
		w = GRAPH_WIDTH;
	} else if (w.tagName) {
	    var left = $(w).offset().left;
	    var winLeft = $(window).scrollLeft()
		if (left < winLeft) left = winLeft;

		var right = left + $(w).outerWidth();
		var winRight = winLeft + $(window).width() - 1;
		if (right > winRight) right = winRight;

		if (x < (left + right) / 2 || (right - x) > 300) {
			w = right - x + 1;
	    } else {
	        w = xl - left;
	        x = left;
	    }
	}
	if (!h) h = GRAPH_HEIGHT;
	var y = yBottom;
	if (y + h > $(window).scrollTop() + $(window).height() - 40) {
		y = yTop - h;
	}
	var $target = $('#graph').css({
		width: (w - gap * 2) + "px",
		height: (h - gap * 2) + "px",
		left: x + "px",
		top: y + "px",
		padding: GRAPH_PADDING + "px",
		borderWidth: GRAPH_BORDER + "px"
	}).show();
	
	var data = new google.visualization.DataTable();
	data.addColumn('string', 'Date');
	$.each(trs, function(i) {
        data.addColumn('number', $(this).children('td:eq(1)').text());
	})
    data.addRows(values.data.length);
    $.each(values.data, function(i) {
    	for (var n = 0; n < this.length; n++) {
    		var val = this[n] !== null? this[n] : 0; // google jsapi has a bug here! we have to set nulls to 0
        	data.setValue(i, n, val);
        }
    });
    var chart = new google.visualization.LineChart($target[0]);
    chart.draw(data, { 
    	width: w - gap * 2, 
    	height: h - gap * 2, 
    	curveType: 'function', 
    	hAxis: { 
    		direction: -1,
    		slantedText: true,
    		maxAlternation: 1,
    		showTextEvery: 1,
    		textStyle: { fontSize: 12 }    		
    	},
    	series: values.series,
    	vAxes: values.vAxes,
    	interpolateNulls: true,
    	pointSize: 10,
    	legend: 'top'
    });
    return $target[0];
}


function hideGraph(graph) {
	if (!graph) return;
	$(graph).hide();
}


$.fn.extend({
    // Runs much faster than default toggle() or show/hide.
    fastToggle: function(disp, onOff) {
        this.each(function() {
            if (onOff !== undefined) {
                this.style.display = (onOff? disp : 'none');
            } else {
                this.style.display = ($(this).css('display') == 'none'? disp : 'none');
            }
        })
    },
    // Disables text selection e.g. on double-click.
    disableSelection: function() {
        this.each(function() {
            this.onselectstart = function() { return false; };
            this.unselectable = "on";
            $(this).css('-moz-user-select', 'none');
        });
        return this;
    }
});


//
// Initialize checkboxes & graphs.
//
google.load('visualization', '1.0', {'packages':['corechart']});
google.setOnLoadCallback(function() {
	google.loaded = true;
});
$(function() {
	if (!$('.chk').length) return;

	if ($(".archived")[0]) {
	    var $showHideDiv = $('#showHideDiv');
	    var archivedShown = false;
		$showHideDiv.show();
		if (location.hash.match(/^#(\d+)$/)) $('.id' + RegExp.$1).show();
		$showHideDiv.find('span').disableSelection().mousedown(function() {
		    archivedShown = !archivedShown;
			$('tr.archived').fastToggle('table-row', archivedShown);
			$showHideDiv.find('span').show();
			$(this).hide();
		});
	}
	
	$(".table_data tr").click(function() {
		$(this).toggleClass('clicked');
	});
	
	 
	var lastCheckedChks = $('.chk:checked');
	var lastClickedChk = null;
	var lastShownGraph = null;

	$('.chk').closest('td').click(function(e) { 
		if (e.target.tagName == 'INPUT') return;
		$(this).find('.chk')[0].click(e);
	});

	$('.chk').click(function(e) {
		var chk = this;
		
		if (e.shiftKey && lastClickedChk) {
			var $chks = $(".chk:visible");
			var p1 = $chks.index(lastClickedChk);
			var p2 = $chks.index(chk);
			if (p1 > p2) { tmp = p1; p1 = p2; p2 = tmp; }
			for (var i = p1; i <= p2; i++) {
				$chks[i].checked = chk.checked;
			}
		}

		lastCheckedChks = $.grep(lastCheckedChks, function(e) { return e != chk });
		
		if (window.google && google.loaded) {
			var lastCheckedChk = lastCheckedChks.length? lastCheckedChks[lastCheckedChks.length - 1] : null;
			var rowsToUse = [];
			if (chk.checked) {
				rowsToUse.push($(chk).closest('tr')[0]);
			}
			if (lastCheckedChk && lastCheckedChk != chk && lastCheckedChk.checked) {
				rowsToUse.push($(lastCheckedChk).closest('tr')[0]);
			}
			if (lastShownGraph) {
				hideGraph(lastShownGraph);
				lastShownGraph = null;
			}
			var showAtChk = chk.checked? chk : (lastCheckedChk && lastCheckedChk.checked? lastCheckedChk : null);
			if (showAtChk) {
				var $td = $(showAtChk).closest('td'); 
				var ofs = $td.offset();
				lastShownGraph = showGraph(
					rowsToUse, 
					ofs.left + $td.outerWidth(),
					ofs.left,
					ofs.top, ofs.top + $td.outerHeight(),
					$td.closest('tr')[0]
				);
			}
		}
		
		if (chk.checked) {
			$(chk).closest('tr').addClass('checked');
			lastCheckedChks.push(chk);
		} else {
			$(chk).closest('tr').removeClass('checked');
		}

		lastClickedChk = chk;
		setTimeout(function() { $(chk).closest('tr').removeClass('clicked') }, 100);
	});
});


//
// Initialize CodeMirror
// 
$(function() {
	if (!$('#sql').length) return;

	var $sql = $('#sql');
	var commentsWidth = $sql.closest('tr').find('.comment').width();
	var captionWidth = $sql.closest('tr').find('td:first').width();
	var paddingsAndGaps = 48;
	
	window.editor = CodeMirror.fromTextArea($sql[0], {
		lineNumbers: false,
		matchBrackets: true,
		indentUnit: 4,
		indentWithTabs: false,
		tabMode: "shift",
		enterMode: "keep",
		mode: "text/x-plsql",
		theme: "neat",
		onFocus: function() { setTimeout(function() { document.body.scrollTop = 0 }, 100) }
	});
	
	function adjustEditorSize() {
		var $e = $('.CodeMirror-scroll');
		if (!$e.length) $e = $sql;
		var heightWithoutEditor = $e.offset().top + $('#action_bar').outerHeight();
		var editorHeight = $(window).height() - heightWithoutEditor - 25;
		if (editorHeight < 100) editorHeight = 100;
		var editorWidth = $(window).width() - commentsWidth - captionWidth - paddingsAndGaps;
		$e.css("height", editorHeight + "px");
		$e.css("width", editorWidth + "px");
	}

	adjustEditorSize();
	$(window).resize(adjustEditorSize);
});


//
// Initialize SQL checker.
// 
$(function() {
	if (!$('#ajax_test_sql_result').length) return;
	
	var $sql = $('#sql');
	var $ajaxTestSqlResult = $('#ajax_test_sql_result');
	var $dsnId = $('#dsn_id');
	var $codeMirror = $('.CodeMirror');
	
	function getSqlValue() {
		if (window.editor) return editor.getValue();
		else return $sql.val();
	}

	var lastXhr = null;
	function testSql(resultDiv, sql) {
		var $div = $(resultDiv);
		if (lastXhr) { 
			lastXhr.abort();
			lastXhr = null;
		}
		lastXhr = $.post("ajax_test_sql.php", 
			{ sql: sql, dsn_id: $dsnId.val() }, 
			function(data) {
				$codeMirror.removeClass("sql_error");
				$codeMirror.removeClass("sql_ok");
				if (data.error) {
					$div.find('.error').text(data.error);
					$div.show();
					$codeMirror.addClass("sql_error");
				} else {
					$div.hide();
					$codeMirror.addClass("sql_ok");
				}
				lastXhr = null;
			}
		);
	}

	var lastSqlValue = getSqlValue();
	var lastSqlTimer = null;
	setInterval(function() {
		var value = getSqlValue();
		if (value != lastSqlValue) {
			lastSqlValue = value;
			if (lastSqlTimer) {
				clearTimeout(lastSqlTimer);
				lastSqlTimer = null;
			}
			lastSqlTimer = setTimeout(function() {
				testSql($ajaxTestSqlResult[0], getSqlValue());
			}, 400);
		}
	}, 100);
	testSql($ajaxTestSqlResult[0], getSqlValue());
	
	$dsnId.change(function() {
		testSql($ajaxTestSqlResult[0], getSqlValue());
	});
});
