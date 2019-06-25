<?php
namespace BridgewaterCollege\Bundle\CanvasApiBundle\Utils;

use Symfony\Component\HttpKernel\Exception\HttpException;

// Additional Includes (Bundle Specific):
use BridgewaterCollege\Bundle\CanvasApiBundle\Entity\CanvasConfiguration;

class CanvasApiServiceHandler extends ProcessHandler {

    private $canvasConfiguration;
    private $headers;

    public function __call($method, $arguments) {
        if (method_exists($this, $method)) {
            if ($this->canvasConfiguration == '')
                $this->getCanvasConfiguration();

            $this->headers = array('Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->canvasConfiguration->canvasApiToken);
            return call_user_func_array(array($this,$method),$arguments);
        }
    }

    private function getCanvasConfiguration() {
        $this->canvasConfiguration = $this->em->getRepository(CanvasConfiguration::class)->find($this->container->getParameter('kernel.environment'));
        if ($this->canvasConfiguration == '')
            throw new HttpException(500, 'Error no valid canvas api configuration found in system. Please generate a config before using the handler.');
    }

    private function setCanvasConfiguration($envName, $apiToken, $url) {
        $this->canvasConfiguration = $this->em->getRepository(CanvasConfiguration::class)->find($envName);
        if ($this->canvasConfiguration == null)
            $this->canvasConfiguration = new CanvasConfiguration();

        $this->canvasConfiguration->canvasApiEnv = $envName;
        $this->canvasConfiguration->canvasApiToken = $apiToken;
        $this->canvasConfiguration->canvasApiUrl = $url;
        $this->canvasConfiguration->setLastModifiedDateTime("now");
        $this->em->merge($this->canvasConfiguration);
        $this->em->flush();
    }

    /** PUT/POST Functions: **/
    private function createUser(array $userDataArray) {
        $data = $userDataArray;

        $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/accounts/self/users', $this->headers, $data);
        $responseObj = json_decode($response->raw_body);

        return $responseObj;
    }

    private function createCourse($courseName, $courseCode, $sisCourseId, $enableSisReactivation) {
        /**
         * $courseName = course[name] e.g. 2019/FA Metal Sculpt (ART-316-01)
         * $courseCode = course[course_code] 2019/FA ART-316-01
         * $sisCourseId = course[sis_course_id] (custom to each organization)
         * $enableSisReactivation = enable_sis_reactivation (0/1)
         *
         * Description: "createCourse" doesn't pass much information in about the course. The problem is this function is mostly creating a placeholder for the course in canvas' system. You need to run an
         * "updateCourse" afterwards once this passes back a valid canvas id to know which course to update.
         */
        $data = array (
            'course[name]' => $courseName,
            'course[course_code]' => $courseCode,
            'course[sis_course_id]' => $sisCourseId,
            'enable_sis_reactivation' => $enableSisReactivation
        );

        $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/accounts/self/courses', $this->headers, $data);
        $responseObj = json_decode($response->raw_body);

        return $responseObj;
    }

    private function updateCourse($canvasId, $sisId, array $courseDataArray) {
        /**
         * Documentation: https://canvas.instructure.com/doc/api/courses.html#method.courses.update
         * $courseDataArray = array of request parameters
         * course[account_id] = the "account id" to move the course under if not the root account (query for it with getAccount)
         * course[name] = e.g. 2019/FA Metal Sculpt (ART-316-01)
         * course[course_code] = e.g. 2019/FA ART-316-01
         * course[term_id] = e.g. 2019/FA (this may be a sis_term_id so you'll have to query for the term canvas id first)
         * course[time_zone] = Eastern Time (US & Canada)
         */
        if ($canvasId != null) {
            $response = \Unirest\Request::put($this->canvasConfiguration->canvasApiUrl.'/api/v1/courses/'.$canvasId, $this->headers, $courseDataArray);
        } else if ($sisId != null) {
            $response = \Unirest\Request::put($this->canvasConfiguration->canvasApiUrl.'/api/v1/courses/sis_course_id:'.$sisId, $this->headers, $courseDataArray);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasId or sisId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    private function createSection($canvasCourseId, $sisCourseId, array $sectionDataArray) {
        /**
         * $canvasCourseId = canvas CourseId to link the section under
         * $sisCourseId = custom sisCourseId to link the section under
         *
         *  $sectionDataArray = array e.g.
         * 'course_section[name]' => 'My Section Name',
         * 'course_section[sis_section_id]' => 02, // your custom sis_section_id
         * 'enable_sis_reactivation' => 1
         */
        if ($canvasCourseId != null) {
            $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/courses/'.$canvasCourseId.'/sections', $this->headers, $sectionDataArray);
        } else if ($sisCourseId != null) {
            //$sectionDataArray = json_encode($sectionDataArray);
            $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/courses/sis_course_id:'.$sisCourseId.'/sections', $this->headers, $sectionDataArray);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasId or sisId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    private function enrollSectionUser($canvasSectionId, $sisSectionId, $enrollmentDataArray) {
        if ($canvasSectionId != null) {
            $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/'.$canvasSectionId.'/enrollments', $this->headers, $enrollmentDataArray);
        } else if ($sisSectionId != null) {
            //$sectionDataArray = json_encode($sectionDataArray);
            $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/sis_section_id:'.$sisSectionId.'/enrollments', $this->headers, $enrollmentDataArray);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasId or sisId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    private function crossListSection($canvasSectionId, $sisSectionId, $canvasParentCourseId, $sisParentCourseId) {
        if ($canvasSectionId != null) {
            if ($canvasParentCourseId != null) {
                $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/'.$canvasSectionId.'/crosslist/'.$canvasParentCourseId, $this->headers);
            } else if ($sisParentCourseId != null) {
                $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/'.$canvasSectionId.'/crosslist/sis_course_id:'.$sisParentCourseId, $this->headers);
            } else {
                throw new HttpException(500, 'Error please either pass a valid canvasParentCourseId or sisParentCourseId to query.');
            }
        } else if ($sisSectionId != null) {
            if ($canvasParentCourseId != null) {
                $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/sis_section_id:'.$sisSectionId.'/crosslist/'.$canvasParentCourseId, $this->headers);
            } else if ($sisParentCourseId != null) {
                $response = \Unirest\Request::post($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/sis_section_id:'.$sisSectionId.'/crosslist/sis_course_id:'.$sisParentCourseId, $this->headers);
            } else {
                throw new HttpException(500, 'Error please either pass a valid canvasParentCourseId or sisParentCourseId to query.');
            }
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasSectionId or sisSectionId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    /** GET Functions: */
    private function getCourse($canvasId, $sisId) {
        if ($canvasId != null) {
            $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/courses/'.$canvasId, $this->headers);
        } else if ($sisId != null) {
            $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/courses/sis_course_id:'.$sisId, $this->headers);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasId or sisId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    private function getSection($canvasSectionId, $sisSectionId) {
        if ($canvasSectionId != null) {
            $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/'.$canvasSectionId, $this->headers);
        } else if ($sisSectionId != null) {
            $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/sis_section_id:'.$sisSectionId, $this->headers);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasId or sisId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    private function getEnrollmentTerms() {
        $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/accounts/self/terms', $this->headers);

        $responseObj = json_decode($response->raw_body);
        return $responseObj->enrollment_terms;
    }

    private function getUserEnrollments($canvasUserId, $sisUserId, array $queryParams) {
        if ($canvasUserId != null) {
            $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/users/'.$canvasUserId.'/enrollments', $this->headers, $queryParams);
        } else if ($sisUserId != null) {
            $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/users/sis_user_id:'.$sisUserId.'/enrollments', $this->headers, $queryParams);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasId or sisId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    private function getAccount($canvasAccountId, $sisAccountId) {
        if ($canvasAccountId != null) {
            $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/accounts/'.$canvasAccountId, $this->headers);
        } else if ($sisAccountId != null) {
            $response = \Unirest\Request::get($this->canvasConfiguration->canvasApiUrl.'/api/v1/accounts/sis_account_id:'.$sisAccountId, $this->headers);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasAccountId or sisAccountId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }


    /** Delete Functions: **/
    private function deleteSection($canvasSectionId, $sisSectionId) {
        if ($canvasSectionId != null) {
            $response = \Unirest\Request::delete($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/'.$canvasSectionId, $this->headers);
        } else if ($sisSectionId != null) {
            $response = \Unirest\Request::delete($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/sis_section_id:'.$sisSectionId, $this->headers);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasId or sisId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    private function deleteEnrolledById($enrollmentId, $canvasCourseId, $sisCourseId, $task) {
        if ($canvasCourseId != null) {
            $response = \Unirest\Request::delete($this->canvasConfiguration->canvasApiUrl.'/api/v1/courses/'.$canvasCourseId.'/enrollments/'.$enrollmentId, $this->headers, $task);
        } else if ($sisCourseId != null) {
            $response = \Unirest\Request::delete($this->canvasConfiguration->canvasApiUrl.'/api/v1/courses/sis_course_id:'.$sisCourseId.'/enrollments/'.$enrollmentId, $this->headers, $task);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasId or sisId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }

    private function deCrossListSection($canvasSectionId, $sisSectionId) {
        if ($canvasSectionId != null) {
            $response = \Unirest\Request::delete($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/'.$canvasSectionId.'/crosslist', $this->headers);
        } else if ($sisSectionId != null) {
            $response = \Unirest\Request::delete($this->canvasConfiguration->canvasApiUrl.'/api/v1/sections/sis_section_id:'.$sisSectionId.'/crosslist', $this->headers);
        } else {
            throw new HttpException(500, 'Error please either pass a valid canvasSectionId or sisSectionId to query.');
        }

        $responseObj = json_decode($response->raw_body);
        return $responseObj;
    }
}