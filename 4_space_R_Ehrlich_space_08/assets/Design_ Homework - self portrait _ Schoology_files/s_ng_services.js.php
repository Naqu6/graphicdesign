/**
 * Course angular service provides an interface to s_course endpoints.
 */
sAngular.app.service('Course', ['SHttp', function(SHttp){
  var obj = {};

  obj.getActiveCourses = function(callback){
    SHttp.get('/iapi/course/active', function(response){
      callback(response.courses, response.permissions);
    });
  };

  return obj;
}]);

/**
 * Course angular service provides an interface to s_course endpoints.
 */
sAngular.app.service('GradingGroup', ['SHttp', function(SHttp){
  return {     
    getList : function(courseNid, callback){
      SHttp.get('/iapi/course/grading_groups/' + courseNid, function(response){
        callback(response.gradingGroups);
      });
    }
  }

}]);

/**
 * Competency angular service
 */
sAngular.app.service('Competency', ['$http', function($http){
  var obj = {};

  obj.fetchData = function(courseNid, csmSectionNid, includeHeaders, page, callback){
    var conf = {
      params : {
        page : page
      }
    };
    if(includeHeaders){
      conf.params.include_headers = 1;
      conf.params.page = page;
    }
    if(csmSectionNid){
      conf.params.csm_section_nid = csmSectionNid;
    }
    $http.get('/iapi/course/competency_standards/' + courseNid, conf).success(function(data, status, headers){
      callback(data);
    });
  };

  obj.fetchTagData = function(courseNid, csmSectionNid, tag, includeHeaders, page, callback){
    var conf = {
      params : {
        page : page
      }
    };
    if(includeHeaders){
      conf.params.include_headers = 1;
      conf.params.page = page;
    }
    if(csmSectionNid){
      conf.params.csm_section_nid = csmSectionNid;
    }
    $http.get('/iapi/course/competency_standards_tags/' + courseNid + '/' + tag, conf).success(function(data, status, headers){
      callback(data);
    });
  }

  return obj;
}]);sAngular.app.service('gradingRubrics', ['$rootScope', '$http', function($rootScope, $http){
  var gradingRubrics = {};
  gradingRubrics.opened = false;
  gradingRubrics.rubrics = {};
  gradingRubrics.singleRubrics = {};
  gradingRubrics.singleResourceRubrics = {};
  gradingRubrics.activeItemNid = null;
  gradingRubrics.loadedGrades = {};
  // Rubric editing in these realms are for rubric templates
  var template_realms = ['user', 'group', 'building', 'school'];

	// Resource rubrics are not compatible with the angular templates, and will need some refactoring to get template fields at the root level
	gradingRubrics.refactorResourceRubric = function(rubric){
		rubric.body.rows = rubric.body.template_fields.rubric_rows;
		rubric.body.total_points = rubric.body.template_fields.total_points;
		delete rubric.body.template_fields;
	}
  
  gradingRubrics.createRubric = function(realm, realm_id, rubric){
    // POST rubric for creation
    // Clear out title if this is not a reusable rubric
    if(rubric.is_reusable == false){
      rubric.title = '';
    }
    if ($.inArray(realm, template_realms) != -1){
      // get folder id
      var folder_id = sGetHashPathParam('f');
      var queryParams = folder_id ? '?f=' + folder_id : '';
      $http.post('/iapi/library/rubric/' + realm_id + queryParams, rubric).success(function (savedRubric, status, headers) {
        if (status == 200) {
	        gradingRubrics.refactorResourceRubric(savedRubric);
          $rootScope.$broadcast('gradingRubricSaveSuccess', savedRubric, true);
        }
      });
    } else {
      $http.post('/iapi/grades/all_rubrics/' + realm + '/' + realm_id, rubric).success(function(savedRubric, status, headers){
        if(status == 200){
          gradingRubrics.updateRubricCache(savedRubric.body);
          $rootScope.$broadcast('gradingRubricSaveSuccess', savedRubric.body, true);
        }
      });
    }
  }
  // Due to the cacheing scheme there might be multiple grading rubric caches ( case where a user is on assignment page and loads
  // 8 different assignments, each with a custom rubric attached)
  gradingRubrics.updateRubricCache = function(rubric){
    $.each(gradingRubrics.rubrics, function(cacheKey, cache){
      if(angular.isUndefined(gradingRubrics.rubrics[cacheKey][rubric.id])){
        return;
      }
      gradingRubrics.rubrics[cacheKey][rubric.id] = rubric;
    });
  }
  gradingRubrics.deleteRubricFromCache = function(rubric){
    $.each(gradingRubrics.rubrics, function(cacheKey, cache){
      if(angular.isUndefined(gradingRubrics.rubrics[cacheKey][rubric.id])){
        return;
      }
      delete gradingRubrics.rubrics[cacheKey][rubric.id];
    })
  }
  
  // this function expects editRubrics to be an array - allowing us to do bulk saving against server
  gradingRubrics.saveRubrics = function(realm, realm_id, editRubrics){
    if ($.inArray(realm, template_realms) != -1){
      $.each(editRubrics, function(idx, obj) {
        $http.put('/iapi/library/rubric/' + obj.nid, obj).success(function (savedRubric, status, headers) {
	        gradingRubrics.refactorResourceRubric(savedRubric);
          $rootScope.$broadcast('gradingRubricSaveSuccess', editRubrics, false);
	        gradingRubrics.singleResourceRubrics = {};
        });
      });
    } else {
      var reqBody = {rubrics : editRubrics};
      $http.put('/iapi/grades/all_rubrics/' + realm + '/' + realm_id, reqBody).success(function (savedRubrics, status, headers) {
        // On success make sure to save
        $.each(editRubrics, function (i, rubric) {
          gradingRubrics.updateRubricCache(rubric);
        });
        $rootScope.$broadcast('gradingRubricSaveSuccess', editRubrics, false);
      });
    }
  }
  
  // when editing rubrics you probably want to do a bulk fetch of all rubrics
  gradingRubrics.getRubricsByCourse = function(courseNid, itemNid, callback){    
    var key = courseNid + (itemNid == false ? '' : ('-' + itemNid));
    if(!angular.isDefined(gradingRubrics.rubrics[key])){
      var conf = {params : {}};
      if(itemNid){
        conf.params = {item_nid : itemNid};
      }
      $http.get('/iapi/grades/all_rubrics/course/' + courseNid, conf).success(function(rubrics, status, headers){
        gradingRubrics.rubrics[key] = rubrics.body;
        callback(gradingRubrics.getRubricsDataCopy('rubrics', key));
      });      
    }
    else{
      callback(gradingRubrics.getRubricsDataCopy('rubrics', key));
    }
  }

  // Never let the angular controllers get a reference of what is cached in the service. Controllers add additional properties
  // onto objects like $$hashKey when there is an ng-repeat. Removing that can break things.
  gradingRubrics.getRubricsDataCopy = function(propName, key){
    return angular.copy(gradingRubrics[propName][key]);
  }

	
	
  // When grading rubrics, you just need to get the one relevant one
  gradingRubrics.getRubricById = function(rubricId, callback){
    if(!angular.isDefined(gradingRubrics.singleRubrics[rubricId])){
      $http.get('/iapi/grades/rubric/' + rubricId).success(function(rubric, status, headers){
        gradingRubrics.singleRubrics[rubricId] = rubric.body;
        callback(gradingRubrics.getRubricsDataCopy('singleRubrics', rubricId));
      });
    }
    else{
      callback(gradingRubrics.getRubricsDataCopy('singleRubrics', rubricId));
    }
  }
	
  gradingRubrics.getResourceRubricById = function(realmId, resourceRubricId, callback){
    if(!angular.isDefined(gradingRubrics.singleResourceRubrics[resourceRubricId])){
      $http.get('/iapi/library/rubric/' + resourceRubricId).success(function(rubric, status, headers){
	      gradingRubrics.refactorResourceRubric(rubric);
        gradingRubrics.singleResourceRubrics[resourceRubricId] = rubric.body;
        callback(gradingRubrics.getRubricsDataCopy('singleResourceRubrics', resourceRubricId));
      });
    }
    else{
      callback(gradingRubrics.getRubricsDataCopy('singleResourceRubrics', resourceRubricId));
    }
  }
  
  gradingRubrics.makeGradeInfoKey = function (rubricId, enrollmentId, itemId, submissionId){
    // default value for submissionId = 'none'
    if(angular.isUndefined(submissionId) || submissionId == null){
      submissionId = 'none';
    }
    return rubricId + '-' + enrollmentId + '-' + itemId + submissionId;
  }
  
  gradingRubrics.loadRubricGradeInfo = function(rubricId, enrollmentId, itemId, isComponent, submissionId, callback){
    // default value for submissionId = 'none'
    if(angular.isUndefined(submissionId) || submissionId == null){
      submissionId = 'none';
    }
    var key = gradingRubrics.makeGradeInfoKey(rubricId, enrollmentId, itemId, submissionId);
    if(!angular.isDefined(gradingRubrics.loadedGrades[key])){
      if (isComponent){
        $http.get('/iapi/grades/assessment_component_submission_rubric_grade_info/' + rubricId + '/' + itemId + '/' + enrollmentId + '/' + submissionId).success(function(data, status, headers){
          gradingRubrics.loadedGrades[key] = data.body;
          callback(gradingRubrics.getRubricsDataCopy('loadedGrades', key));
        })
      } else {
        $http.get('/iapi/grades/rubric_grade_info/' + rubricId + '/' + itemId + '/' + enrollmentId).success(function (data, status, headers) {
          gradingRubrics.loadedGrades[key] = data.body;
          callback(gradingRubrics.getRubricsDataCopy('loadedGrades', key));
        })
      }
    }
    else{
      callback(gradingRubrics.getRubricsDataCopy('loadedGrades', key));
    }
  }
  
  gradingRubrics.saveRubricGradeInfo = function(rubricId, enrollmentId, itemId, gradeInfo, overrideGrade, isComponent, submissionId, callback, comment){
    var reqBody = {gradeInfo : gradeInfo, overrideGrade : overrideGrade, comment: comment};
    if (!isComponent) {
      $http.put('/iapi/grades/rubric_grade_info/' + rubricId + '/' + itemId + '/' + enrollmentId, reqBody).success(function (data, status, headers) {
        // update this data in the cache so we have it!
        var key = gradingRubrics.makeGradeInfoKey(rubricId, enrollmentId, itemId, submissionId);
        gradingRubrics.loadedGrades[key] = reqBody;

        var request = {
          rubricId: rubricId,
          enrollmentId: enrollmentId,
          itemId: itemId,
          gradeInfo: gradeInfo,
          overrideGrade: overrideGrade
        };
        var response = angular.isDefined(data.body) && data.body ? data.body : {};
        callback(request, response);
      });
    } else {
      $http.put('/iapi/grades/assessment_component_submission_rubric_grade_info/' + rubricId + '/' + itemId + '/' + enrollmentId + '/' + submissionId, reqBody).success(function (data, status, headers) {
        // update this data in the cache so we have it!
        var key = gradingRubrics.makeGradeInfoKey(rubricId, enrollmentId, itemId, submissionId);
        gradingRubrics.loadedGrades[key] = reqBody;

        var request = {
          rubricId: rubricId,
          enrollmentId: enrollmentId,
          itemId: itemId,
          gradeInfo: gradeInfo,
          overrideGrade: overrideGrade
        };
        var response = angular.isDefined(data.body) && data.body ? data.body : {};
        callback(request, response);
      });
    }
  }
  
  $rootScope.$on('gradingRubricDelete', function(e, data){
    var rubricId = data.arg;
    var delPath = '/iapi/grades/rubric/' + rubricId;
    $http({
      method : 'DELETE',
      url : delPath
    }).success(function(){
      $rootScope.$broadcast('gradingRubricDeleteSuccess', rubricId);
    })
  })
  return gradingRubrics;
}]);

sAngular.app.service('sGrader', ['$rootScope', '$http', function($rootScope, $http){
  var sGrader = {},
      data = {
        loaded: false
      };

  // constants
  sGrader.S_GRADING_SCALE_TYPE_RUBRIC = 2;

  var gradingPeriodLookup = {},
      gradingCategoryLookup = {},
      enrollmentIdLookup = {};

  function resetData(){
    data = {
      loaded: false,
      uids: [],
      user_data: {},
      grade_item_nids: [],
      grade_item_data: {},
      grading_period: {
        id: null,
        title: null
      },
      grading_periods: [],
      grading_category: {
        id: null,
        title: null
      },
      grading_categories: [],
      grading_groups: [],
      grade_item_order: null,
      graded_with_new : []
    };
  }

  function setData(k, v){
    data[k] = v;
  }

  function indexData(){
    $.each(data.grading_periods, function(idx, gp){
      gradingPeriodLookup[gp.id] = idx;
    });
    $.each(data.grading_categories, function(idx, gc){
      gradingCategoryLookup[gc.id] = idx;
    });
  }

  function indexUserData(userData){
    $.each(userData, function(uid, user){
      enrollmentIdLookup[user.enrollment_id] = uid;
    });
  }

  /**
   * Determine if something has changed and will require a reload of data
   */
  function datasetChanged(opts){
    var changed = false,
        gradingPeriodChanged = angular.isDefined(data.grading_period) && opts.grading_period_id != data.grading_period.id,
        gradingCategoryChanged = angular.isDefined(data.grading_category) && opts.grading_category_id != data.grading_category.id,
        gradingGroupChanged = angular.isDefined(data.grading_group_id) && opts.grading_group_id != data.grading_group_id,
        gradeItemOrderChanged = angular.isDefined(data.grade_item_order) && opts.grade_item_order != data.grade_item_order;
    if(gradingPeriodChanged || gradingCategoryChanged || gradingGroupChanged || gradeItemOrderChanged || angular.isDefined(opts.reset)){
      changed = true;
    }

    return changed;
  }

  sGrader.getGradingPeriod = function(id){
    var ret = null;

    if(angular.isDefined(gradingPeriodLookup[id])){
      ret = data.grading_periods[gradingPeriodLookup[id]];
    }

    return ret;
  };

  sGrader.getGradingCategory = function(id){
    var ret = null;

    if(angular.isDefined(gradingCategoryLookup[id])){
      ret = data.grading_categories[gradingCategoryLookup[id]];
    }

    return ret;
  };

  sGrader.getGradedWithNew = function(){
    return data.graded_with_new;
  }

  sGrader.getUser = function(uid){
    var ret = null;
    if(angular.isDefined(data.user_data[uid])){
      ret = data.user_data[uid];
    }

    return ret;
  };

  sGrader.getUidByEnrollmentId = function(enrollmentId){
    return angular.isDefined(enrollmentIdLookup[enrollmentId]) ? enrollmentIdLookup[enrollmentId] : null;
  };

  sGrader.getUserByEnrollmentId = function(enrollmentId){
    var ret = sGrader.getUser(sGrader.getUidByEnrollmentId(enrollmentId));
  };

  sGrader.getGradeItem = function(gradeItemNid){
    var ret = null;
    if(data && gradeItemNid && data.grade_item_data[gradeItemNid]){
      ret = data.grade_item_data[gradeItemNid];
    }

    return ret;
  };

  sGrader.addGradeItemNid = function(gradeItemNid){
    data.grade_item_nids.push(gradeItemNid);
  };

  sGrader.processGradeItem = function(gradeItem){
    if(/gp-\d/.test(gradeItem.id)){
      gradeItem.item_type = gradeItem.type = 'grading_period';
    }
    else{
      gradeItem.type = 'grade_item';
      if(angular.isDefined(gradeItem.status)){
        gradeItem.status_text = gradeItem.status == 2 ? Drupal.t('Unpublished') : Drupal.t('Published');
      }
      if(!gradeItem.grading_category_id && !gradeItem.is_final){
        gradeItem.disabled = 'no_category';
      }
      if(angular.isDefined(gradeItem.has_assessment) && gradeItem.has_assessment){
        // if this is an assessment, check the total component points
        if(angular.isUndefined(gradeItem.component_points) || !gradeItem.component_points){
          gradeItem.disabled = 'no_component_points';
        }
      }

      if(typeof gradeItem.has_assessment != 'undefined' && gradeItem.has_assessment){
        gradeItem.item_type = 'assessment';
      }
      else if(typeof gradeItem.is_discussion != 'undefined' && gradeItem.is_discussion){
        gradeItem.item_type = 'discussion';
      }
      else if (typeof gradeItem.external_tool_link_nid != 'undefined' && typeof gradeItem.external_tool_attachment_id != 'undefined') {
        gradeItem.item_type = 'external_tool';
      }
      else{
        gradeItem.item_type = 'assignment';
      }
    }

    gradeItem.loaded = true;

    return gradeItem;
  };

  sGrader.getHeaderData = function(course_nid, opts, callback){
    var doneFunc = function(){
      if(typeof callback == 'function'){
        callback.call(null, data);
      }
    };

    opts = $.extend({
      grading_period_id: null,
      grading_category_id: null,
      grading_group_id: null,
      grade_item_order: null,
      csm_section_nid: null
    }, opts);
    if(!data.loaded || datasetChanged(opts)){
      resetData();
      var url = '/iapi/grades/grader_header_data/' + course_nid,
          param = {};
      if(opts.grading_period_id){
        url += '/' + opts.grading_period_id;
      }
      $.each(['grading_category_id', 'grading_group_id', 'grade_item_order', 'csm_section_nid'], function(i, key){
        if(opts[key]){
          param[key] = opts[key];
        }
      });
      if(!$.isEmptyObject(param)){
        url += '?' + $.param(param);
      }
      $http.get(url).success(function(response){
        if(angular.isUndefined(response) || angular.isUndefined(response.body)){
          return;
        }
        var body = response.body;

        // data.loaded acts as a semaphore for simultaneous requests to the same grading period
        if(!data.loaded){
          data.loaded = true;
          $.each(body, function(k, value){
            setData(k, value);
          });

          indexData();
          
          if(data.grading_categories.length){
            if(opts.grading_category_id){
              data.grading_category = sGrader.getGradingCategory(opts.grading_category_id);
            }
            else{
              data.grading_category = data.grading_categories[0];
            }
          }

          data.users = {};
          if(data.user_data){
            $.each(data.uids, function(k, uid){
              if(angular.isDefined(data.user_data[uid])){
                data.user_data[uid].uid = uid;
                data.user_data[uid].loaded = true;
                if(angular.isUndefined(data.user_data[uid].comments)){
                  data.user_data[uid].comments = {};
                }
                if(/sample_\d+/.test(uid)){
                  data.user_data[uid].disabled = 'sample';
                }
                data.users[uid] = data.user_data[uid];
              }
              else{
                return false;
              }
            });
            indexUserData(data.users);
          }
          
          data.grade_items = {};
          if(data.grade_item_data){
            $.each(data.grade_item_nids, function(k, grade_item_nid){
              if(angular.isDefined(data.grade_item_data[grade_item_nid])){
                data.grade_items[grade_item_nid] = sGrader.processGradeItem(data.grade_item_data[grade_item_nid]);
              }
              else{
                return false;
              }
            });
          }
          sAngular.rootScopeBroadcast('sGraderHeaderDataChanged', data);
        }

        doneFunc();
      });
    }
    else{
      // everything has already been loaded
      doneFunc();
    }
  };

  sGrader.getGrades = function(course_nid, grading_period_id, query, callback){
    var param = $.extend({
      uids: null,
      grade_item_nids: null
    }, query);

    var csm_section_nid = getHashParam('csm_section_nid');
    if (csm_section_nid) {
      param.csm_section_nid = csm_section_nid
    }

    if(angular.isDefined(query.uids)){
      var uids = [],
          sample_users = [];

      $.each(query.uids, function(k, uid){
        if(!sGrader.getUser(uid)){
          // 1 uses less characters than true when translated into query string
          param.with_user_data = 1;
        }
        if(/sample_\d+/.test(uid)){
          sample_users.push(uid);
        }
        else{
          uids.push(uid);
        }
      });

      if(uids.length){
        param.uids = uids.join(',');
      }
      if(sample_users.length){
        param.sample_users = sample_users.join(',');
      }
    }
    if(angular.isDefined(query.grade_item_nids)){
      param.grade_item_nids = query.grade_item_nids.join(',');
      $.each(query.grade_item_nids, function(k, gradeItemNid){
        if(!sGrader.getGradeItem(gradeItemNid)){
          param.with_grade_item_data = 1;
          return false;
        }
      });
    }

    var url = '/iapi/grades/grader_grade_data/' + course_nid + '/' + grading_period_id;
    url += '?' + $.param(param);
    
    // using the plain $http since we don't want cache here
    $http.get(url).success(function(response){
      var cbParam = {};
      if(angular.isDefined(response) && angular.isDefined(response.body)){
        var body = response.body;
        if(angular.isDefined(query.uids) && angular.isDefined(query.grade_item_nids)){
          if(angular.isDefined(body.user_data)){
            cbParam.users = {};
            $.each(query.uids, function(k, uid){
              if(angular.isDefined(body.user_data[uid])){
                // if the response has the user data for this user, populate our cache with it
                data.user_data[uid] = body.user_data[uid];
                data.user_data[uid].uid = uid;
                data.user_data[uid].loaded = true;
                if(angular.isUndefined(data.user_data[uid].comments)){
                  data.user_data[uid].comments = {};
                }
                indexUserData(data.user_data);
              }
              if(/sample_\d+/.test(uid)){
                data.user_data[uid].disabled = 'sample';
              }
              cbParam.users[uid] = data.user_data[uid];
            });
          }

          if(angular.isDefined(body.grade_item_data)){
            cbParam.grade_items = {};
            $.each(query.grade_item_nids, function(k, nid){
              if(angular.isDefined(body.grade_item_data[nid])){
                // if the response has the grade item data for this nid, populate our cache with it
                data.grade_item_data[nid] = body.grade_item_data[nid];
              }
              cbParam.grade_items[nid] = sGrader.processGradeItem(data.grade_item_data[nid]);
            });
          }

          cbParam.grades = body.grades;
        }
      }
      else{
        sAngular.rootScopeBroadcast('sGradesGraderError', {
          code: 'invalid_response'
        });
      }

      if(typeof callback == 'function'){
        callback.call(null, cbParam);
      }
    });
  };

  sGrader.getGradeItemNids = function(){
    return data.grade_item_nids;
  };

  sGrader.getNumGradeItemNids = function(){
    return _.unique(data.grade_item_nids).length;
  };

  sGrader.getUids = function(){
    return data.uids;
  };

  sGrader.getNumUids = function(){
    return data.uids.length;
  };

  return sGrader;
}]);sAngular.app.service('Enrollment', ['$http', function($http){
    var enrollment = {};
    enrollment.enrollments = {};
    enrollment.getEnrollments = function(realm, realm_id, callback){
      var key = realm + '-' + realm_id;
      if(angular.isUndefined(enrollment.enrollments[key])){
        $http.get('/iapi/enrollment/member_enrollments/' + realm + '/' + realm_id).success(function(data,status,headers){
          enrollment.enrollments[key] = data.body;
          callback(enrollment.enrollments[key]);
        })
      }
      else{
        callback(enrollment.enrollments[key]);
      }
    }
    return enrollment;
}]);sAngular.app.service('Calendar', ['SHttp', '$http', function(SHttp, $http){
  var obj = {};

  obj.getRealmSettings = function(callback){
    var url = '/iapi/event/calendar_realm_settings';
    SHttp.get(url, function(response){
      callback(response.settings);
    });
  };
  
  obj.saveRealmSettings = function(data, callback){
    var url = '/iapi/event/calendar_realm_settings';
    $http.post(url, data).success(function(response){
      callback(response);
    });
  };

  return obj;
}]);sAngular.app.service('sParent', ['$rootScope', 'SHttp', function($rootScope, SHttp){
  var sParent = {},
    currentChild,
    children;

  function getInfo(callback){
    SHttp.get('/iapi/parent/info', function(body){
      if(angular.isUndefined(children)){
        children = body.children;
        $.each(children, function(i, child){
          child.enrollments = {};
          child.totalRecentCounts = function(){
            var total = 0;

            $.each(this.recent_counts, function(k, v){
              total += v;
            });

            return total;
          };
          child.recent_grades = {};
        });
      }
      if(angular.isUndefined(currentChild)){
        if(body.session.view_child){
          currentChild = children[body.session.view_child];
        }
        else{
          currentChild = null;
        }
      }
      if(typeof callback == 'function'){
        callback(body);
      }
    });
  }

  sParent.getCurrentChild = function(callback){
    if(angular.isUndefined(currentChild)){
      getInfo(function(){
        callback(currentChild);
      });
    }
    else{
      callback(currentChild);
    }
  };

  sParent.getChildren = function(callback){
    if(angular.isUndefined(children)){
      getInfo(function(){
        callback(children);
      });
    }
    else{
      callback(children);
    }
  };

  sParent.getChild = function(child_uid, callback){
    sParent.getChildren(function(){
      var child = angular.isDefined(children[child_uid]) ? children[child_uid] : null;
      callback(child);
    });
  };

  sParent.updateCurrentChild = function(child_uid, callback){
    currentChild = angular.isDefined(children[child_uid]) ? children[child_uid] : null;
    if(typeof callback == 'function'){
      callback(currentChild);
    }
  };

  sParent.getEnrollmentsForChild = function(child_uid, enrollment_type, callback){
    sParent.getChildren(function(){
      var enrollments = [];
      if(angular.isDefined(children[child_uid])){
        var child = children[child_uid];
        if(angular.isDefined(child.enrollments[enrollment_type])){
          enrollments = child.enrollments[enrollment_type];
          if(typeof callback == 'function'){
            callback(enrollments);
          }
        }
        else{
          child.enrollments[enrollment_type] = [];
          // the enrollments for the child was not previously fetched, hit the server for it
          SHttp.get('/iapi/parent/enrollments/' + child_uid + '/' + enrollment_type, function(data){
            child.enrollments[enrollment_type] = data.enrollments;
            if(typeof callback == 'function'){
              callback(child.enrollments[enrollment_type]);
            }
          });
        }
      }
      else{
        if(typeof callback == 'function'){
          callback(enrollments);
        }
      }
    });
  };

	sParent.getOverdueSubmissions = function(child_uid, callback){
		SHttp.get('/iapi/parent/overdue_submissions/' + child_uid, function(data){
			if(typeof callback == 'function'){
				callback(data.html);
			}
		});
	};

  sParent.getRecentFeedCountsForChild = function(child_uid, callback){
    sParent.getChild(child_uid, function(child){
      if(typeof callback == 'function'){
        callback(child.recent_counts);
      }
    });
  };

  sParent.getRecentGrades = function(child_uid, category, opts){
    var defaults = {
      page: 0,
      callback: null
    };
    opts = $.extend({}. defaults, opts);
    sParent.getChild(child_uid, function(child){
      var fetchNextPage = false,
          child_data = null;
      if(angular.isDefined(child.recent_grades[category])){
        child_data = child.recent_grades[category];
        if(child_data.has_more && child_data.last_page < opts.page){
          fetchNextPage = true;
        }
        else{
          if(typeof opts.callback == 'function'){
            opts.callback(child.recent_grades[category]);
          }
        }
      }
      else{
        child_data = child.recent_grades[category] = {
          has_more: false,
          last_page: 0,
          data: []
        };
        fetchNextPage = true;
      }

      if(fetchNextPage){
        var param = {
          page: opts.page
        };
        child_data.has_more = false;
        if(angular.isDefined(child_data.last_checked)){
          param.last_checked = child_data.last_checked;
        }
        SHttp.get('/iapi/parent/recent/' + child.uid + '/' + category + '?' + $.param(param), function(data){
          $.merge(child_data.data, data.results);
          child_data.has_more = data.has_more;
          child_data.last_page = param.page;
          child_data.last_checked = data.last_checked;
          if(typeof opts.callback == 'function'){
            opts.callback(child.recent_grades[category]);
          }
        });
      }
    });
  };

  sParent.getRecentAttendance = function(child_uid, category, callback){
    sParent.getChild(child_uid, function(child){
      if(angular.isDefined(child.recent_attendance)){
        if(typeof callback == 'function'){
          callback(child.recent_attendance);
        }
      }
      else{
        SHttp.get('/iapi/parent/recent/' + child.uid + '/' + category, function(data){
          child.recent_attendance = data;
          if(typeof callback == 'function'){
            callback(child.recent_attendance);
          }
        });
      }
    });
  };

  return sParent;
}]);sAngular.app.service('sPagesIndex', ['SHttp', '$http', function(SHttp, $http){
  var obj = {};

  obj.get = function(options){
    var url = '/iapi/page/index/' + options.realm + '/' + options.realmId + '?offset=' + options.offset;
    SHttp.get(url, function(response){
      options.callback(response);
    });
  };

  obj.saveReorder = function(options){
    var url = '/iapi/page/reorder/' + options.realm + '/' + options.realmId;
    $http.post(url, options.data).success(function(response){
      options.callback(response);
    });
  };

  return obj;
}]);