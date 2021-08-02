<?php
namespace  Drupal\jquery_ajax_load;

use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\ControllerDoesNotReturnResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class JqueryAjaxLoad {

  /**
   * @var EventDispatcherInterface
   */
  protected $eventDispatcher;
  /**
   * @var ControllerResolverInterface
   */
  protected $controllerResolver;
  protected $requestStack;

  /**
   * @var ArgumentResolverInterface
   */
  protected $argumentResolver;

  public function __construct(EventDispatcherInterface $eventDispatcher, ControllerResolverInterface $controllerResolver, ArgumentResolverInterface $argumentResolver)
  {
    $this->eventDispatcher = $eventDispatcher;
    $this->controllerResolver = $controllerResolver;
    $this->argumentResolver = $argumentResolver;
    $this->requestStack = \Drupal::requestStack();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('controller_resolver'),
      $container->get('http_kernel.controller.argument_resolver')
    );
  }

  public function getKernelHandleRaw(Request $request)
  {
    global $kernel;
    $type = DrupalKernel::MASTER_REQUEST;
    $this->requestStack->push($request);

    // load controller
    if (false === $controller = $this->controllerResolver->getController($request)) {
      throw new NotFoundHttpException(sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $request->getPathInfo()));
    }

    $event = new ControllerEvent($kernel, $controller, $request, $type);
    $this->eventDispatcher->dispatch($event, KernelEvents::CONTROLLER);
    $controller = $event->getController();

    // controller arguments
    $arguments = $this->argumentResolver->getArguments($request, $controller);

    $event = new ControllerArgumentsEvent($kernel, $controller, $arguments, $request, $type);
    $this->eventDispatcher->dispatch($event, KernelEvents::CONTROLLER_ARGUMENTS);
    $controller = $event->getController();
    $arguments = $event->getArguments();

    // call controller
    $response = $controller(...$arguments);
    if ($response) {
      return $response;
    }
    return NULL;
  }

  public function getResponse(Request $request, $return_rendererable_array = FALSE)
  {
    global $kernel;
    $type = DrupalKernel::MASTER_REQUEST;
    $this->requestStack->push($request);

    // request
    $event = new RequestEvent($kernel, $request, $type);
    $this->eventDispatcher->dispatch($event, KernelEvents::REQUEST);

    if ($event->hasResponse() && !$return_rendererable_array) {
      return $this->filterResponse($event->getResponse(), $request, $type);
    }

    // load controller
    if (false === $controller = $this->controllerResolver->getController($request)) {
      throw new NotFoundHttpException(sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $request->getPathInfo()));
    }

    $event = new ControllerEvent($kernel, $controller, $request, $type);
    $this->eventDispatcher->dispatch($event, KernelEvents::CONTROLLER);
    $controller = $event->getController();

    // controller arguments
    $arguments = $this->argumentResolver->getArguments($request, $controller);

    $event = new ControllerArgumentsEvent($kernel, $controller, $arguments, $request, $type);
    $this->eventDispatcher->dispatch($event, KernelEvents::CONTROLLER_ARGUMENTS);
    $controller = $event->getController();
    $arguments = $event->getArguments();

    // call controller
    $response = $controller(...$arguments);
    if ($return_rendererable_array) {
      $event = new ViewEvent($kernel, $request, $type, $response);
      $this->eventDispatcher->dispatch($event, KernelEvents::VIEW);
      return $response;
    }

    // view
    if (!$response instanceof Response) {
      $event = new ViewEvent($kernel, $request, $type, $response);
      $this->eventDispatcher->dispatch($event, KernelEvents::VIEW);

      if ($event->hasResponse()) {
        $response = $event->getResponse();
      } else {
        $msg = sprintf('The controller must return a "Symfony\Component\HttpFoundation\Response" object but it returned %s.', $this->varToString($response));

        // the user may have forgotten to return something
        if (null === $response) {
          $msg .= ' Did you forget to add a return statement somewhere in your controller?';
        }

        throw new ControllerDoesNotReturnResponseException($msg, $controller, __FILE__, __LINE__ - 17);
      }
    }

    return $this->filterResponse($response, $request, $type);
  }

  /**
   * Filters a response object.
   *
   * @throws \RuntimeException if the passed object is not a Response instance
   */
  private function filterResponse(Response $response, Request $request, int $type): Response
  {
    global $kernel;
    $event = new ResponseEvent($kernel, $request, $type, $response);

    $this->eventDispatcher->dispatch($event, KernelEvents::RESPONSE);

    $this->finishRequest($request, $type);

    return $event->getResponse();
  }

  /**
   * Publishes the finish request event, then pop the request from the stack.
   *
   * Note that the order of the operations is important here, otherwise
   * operations such as {@link RequestStack::getParentRequest()} can lead to
   * weird results.
   */
  private function finishRequest(Request $request, int $type)
  {
    global $kernel;
    $this->eventDispatcher->dispatch(new FinishRequestEvent($kernel, $request, $type), KernelEvents::FINISH_REQUEST);
    $this->requestStack->pop();
  }
  /**
   * Returns a human-readable string for the specified variable.
   */
  private function varToString($var): string
  {
    if (\is_object($var)) {
      return sprintf('an object of type %s', \get_class($var));
    }

    if (\is_array($var)) {
      $a = [];
      foreach ($var as $k => $v) {
        $a[] = sprintf('%s => ...', $k);
      }

      return sprintf('an array ([%s])', mb_substr(implode(', ', $a), 0, 255));
    }

    if (\is_resource($var)) {
      return sprintf('a resource (%s)', get_resource_type($var));
    }

    if (null === $var) {
      return 'null';
    }

    if (false === $var) {
      return 'a boolean value (false)';
    }

    if (true === $var) {
      return 'a boolean value (true)';
    }

    if (\is_string($var)) {
      return sprintf('a string ("%s%s")', mb_substr($var, 0, 255), mb_strlen($var) > 255 ? '...' : '');
    }

    if (is_numeric($var)) {
      return sprintf('a number (%s)', (string) $var);
    }

    return (string) $var;
  }
}
